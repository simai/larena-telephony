<?php

declare(strict_types=1);

namespace Larena\Telephony\Adapters\AsteriskAmi;

use Larena\Telephony\Adapters\AsteriskAmi\Contracts\AmiContextResolver;
use Larena\Telephony\Adapters\AsteriskAmi\Contracts\AmiTransport;
use Larena\Telephony\Adapters\AsteriskAmi\Support\AmiDiagnosticRedactor;
use Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiAction;
use Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiCommandTarget;
use Larena\Telephony\Contracts\TelephonyAdapter;
use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\ContractVersion;
use Larena\Telephony\Support\TelephonyContract;
use Larena\Telephony\ValueObjects\AdapterManifest;
use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\CommandResult;
use Larena\Telephony\ValueObjects\EndpointDescriptor;
use Larena\Telephony\ValueObjects\EndpointValidation;
use Larena\Telephony\ValueObjects\EventEnvelope;
use Larena\Telephony\ValueObjects\EventMappingResult;
use Larena\Telephony\ValueObjects\HealthReport;
use Larena\Telephony\ValueObjects\ProviderDescriptor;
use Larena\Telephony\ValueObjects\ProviderEvent;
use Throwable;

final readonly class AsteriskAmiAdapter implements TelephonyAdapter
{
    private AdapterManifest $descriptor;

    public function __construct(
        private AmiTransport $transport,
        private AmiContextResolver $contexts,
        string $adapterKey = 'larena.asterisk_ami',
        string $providerId = 'org.asterisk.ami',
        string $composerPackage = 'larena/telephony',
        string $contractVersion = TelephonyContract::VERSION,
        bool $truthfulCapabilities = true,
    ) {
        $handlers = [
            'originate' => self::class.'::dispatchOriginate',
            'hangup' => self::class.'::dispatchHangup',
            'transfer' => self::class.'::dispatchTransfer',
            'dtmf' => self::class.'::dispatchDtmf',
        ];
        if (!$truthfulCapabilities) {
            $handlers['answer'] = self::class.'::dispatchAnswer';
        }
        $this->descriptor = new AdapterManifest(
            $adapterKey,
            new ProviderDescriptor($providerId, 'Asterisk AMI 22 LTS reference adapter', 'experimental', ['ami_22_fixture'], 'residency.endpoint_defined'),
            $composerPackage,
            '1.1.0',
            $contractVersion,
            'experimental',
            ['calls.dtmf', 'calls.hangup', 'calls.originate', 'calls.transfer', 'events.ingest', 'health.probe'],
            'larena://telephony/schemas/asterisk-ami-22-endpoint-v1',
            self::class.'::mapEvent',
            $handlers,
            self::class.'::health',
            'larena://telephony/redaction/asterisk-ami-v1',
        );
    }

    public function manifest(): AdapterManifest
    {
        return $this->descriptor;
    }

    public function validateEndpoint(EndpointDescriptor $endpoint): EndpointValidation
    {
        $errors = [];
        if ($endpoint->adapterKey !== $this->descriptor->adapterKey) {
            $errors[] = 'adapter_key_mismatch';
        }
        if (!ContractVersion::supports($endpoint->contractVersion)) {
            $errors[] = 'incompatible_contract_version';
        }
        foreach (array_keys($endpoint->configuration) as $key) {
            if (!in_array($key, ['ami_series', 'routing_profile_ref', 'simulated_health'], true)) {
                $errors[] = 'unknown_endpoint_field:'.$key;
            }
        }
        if (($endpoint->configuration['ami_series'] ?? null) !== '22.x') {
            $errors[] = 'asterisk_ami_series_must_be_22_x';
        }
        $routingProfile = $endpoint->configuration['routing_profile_ref'] ?? null;
        if (!is_string($routingProfile) || preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)+$/', $routingProfile) !== 1) {
            $errors[] = 'routing_profile_ref_required';
        }
        if (($endpoint->secretRefs['connection.primary'] ?? null) === null || count($endpoint->secretRefs) !== 1) {
            $errors[] = 'connection_secret_ref_required';
        }
        foreach ($endpoint->effectiveCapabilities as $capability) {
            if (!in_array($capability, $this->descriptor->capabilities, true)) {
                $errors[] = 'unsupported_effective_capability:'.$capability;
            }
        }

        return $errors === [] ? EndpointValidation::valid() : EndpointValidation::invalid($errors);
    }

    public function mapEvent(ProviderEvent $event): EventMappingResult
    {
        if ($event->adapterKey !== $this->descriptor->adapterKey) {
            return EventMappingResult::quarantined('adapter_key_mismatch');
        }
        $providerType = $event->payload['Event'] ?? null;
        if (!is_string($providerType) || $providerType === '') {
            return $this->quarantine('malformed_provider_event', $event);
        }

        $mapping = $this->eventMapping($providerType, $event->payload);
        if ($mapping === null) {
            $known = in_array($providerType, ['DialEnd', 'Newstate'], true);

            return $this->quarantine($known ? 'unmapped_provider_event_semantics' : 'unknown_provider_event', $event);
        }

        $linkedId = $event->payload['Linkedid'] ?? null;
        $uniqueId = $event->payload['Uniqueid'] ?? null;
        $logicalCallRef = is_string($linkedId) && $linkedId !== ''
            ? $linkedId
            : (is_string($uniqueId) && $uniqueId !== '' ? $uniqueId : null);
        $context = $this->contexts->eventContext($event);
        if ($logicalCallRef === null || $context === null) {
            return $this->quarantine('malformed_provider_event', $event);
        }

        $eventHash = hash('sha256', $event->providerEventRef);
        $callHash = hash('sha256', $logicalCallRef);
        $sequence = $event->payload['Sequence'] ?? null;
        if (is_string($sequence) && ctype_digit($sequence)) {
            $sequence = (int) $sequence;
        }

        return EventMappingResult::accepted(new EventEnvelope(
            'larena.event.'.substr($eventHash, 0, 24),
            $mapping['event_type'],
            TelephonyContract::VERSION,
            $context->tenantRef,
            $event->endpointRef,
            $event->adapterKey,
            'sha256:'.$eventHash,
            'sha256:'.$callHash,
            $event->occurredAt,
            $event->occurredAt,
            is_int($sequence) ? $sequence : null,
            'provider-event:'.$eventHash,
            'correlation.event.'.substr($eventHash, 0, 16),
            [
                'direction' => $context->direction,
                'local_party' => $context->localParty->toArray(),
                'remote_party' => $context->remoteParty->toArray(),
                'state' => $mapping['state'],
            ],
        ));
    }

    public function dispatch(CallCommand $command): CommandResult
    {
        if (!$this->descriptor->supportsCommand($command->commandType)) {
            return new CommandResult('unsupported', 'command_not_supported', $command->correlationId);
        }
        try {
            $endpoint = $this->contexts->endpoint($command->endpointRef);
            $target = $this->contexts->commandTarget($command);
        } catch (ContractViolation $exception) {
            return new CommandResult('failed', $exception->reasonCode, $command->correlationId);
        }
        if ($endpoint === null || !$target instanceof AmiCommandTarget || !$this->validateEndpoint($endpoint)->valid) {
            return new CommandResult('failed', 'ami_command_context_unavailable', $command->correlationId);
        }
        $capability = TelephonyContract::COMMAND_CAPABILITIES[$command->commandType];
        if (!in_array($capability, $endpoint->effectiveCapabilities, true)) {
            return new CommandResult('failed', 'endpoint_capability_unavailable', $command->correlationId);
        }

        try {
            $action = $this->action($command, $target);
        } catch (ContractViolation $exception) {
            return new CommandResult('failed', $exception->reasonCode, $command->correlationId);
        }

        try {
            $result = $this->transport->send($endpoint, $action);
        } catch (Throwable) {
            return new CommandResult('uncertain', 'ami_transport_exception', $command->correlationId);
        }
        if ($result->status === 'acknowledged') {
            if ($result->actionId !== $action->actionId) {
                return new CommandResult('uncertain', 'ami_response_correlation_missing', $command->correlationId);
            }

            return new CommandResult(
                'succeeded',
                'ami_action_acknowledged_not_call_outcome',
                $command->correlationId,
                $result->responseReceiptHash,
            );
        }
        if ($result->status === 'rejected') {
            return new CommandResult('failed', 'ami_action_rejected', $command->correlationId, $result->responseReceiptHash);
        }

        return new CommandResult('uncertain', 'ami_action_outcome_uncertain', $command->correlationId, $result->responseReceiptHash);
    }

    public function health(EndpointDescriptor $endpoint): HealthReport
    {
        if (!$this->validateEndpoint($endpoint)->valid) {
            return new HealthReport('unavailable', 'endpoint_configuration_invalid');
        }
        $status = $endpoint->configuration['simulated_health'] ?? 'unknown';
        if (!is_string($status) || !in_array($status, TelephonyContract::HEALTH_STATUSES, true)) {
            $status = 'unknown';
        }

        return new HealthReport($status, 'offline_fixture_'.$status);
    }

    public function redactDiagnostic(array $diagnostic): array
    {
        return AmiDiagnosticRedactor::redact($diagnostic);
    }

    public static function actionId(CallCommand $command): string
    {
        return 'larena-'.substr(hash('sha256', $command->tenantRef."\0".$command->commandId."\0".$command->idempotencyKey), 0, 24);
    }

    private function action(CallCommand $command, AmiCommandTarget $target): AmiAction
    {
        $id = self::actionId($command);

        return match ($command->commandType) {
            'originate' => new AmiAction('Originate', $id, [
                'Async' => true,
                'Channel' => $target->channel,
                'Context' => $target->dialplanContext,
                'Exten' => $target->extension,
                'Priority' => $target->priority,
                'Timeout' => $target->timeoutMs,
            ]),
            'hangup' => new AmiAction('Hangup', $id, ['Channel' => $target->channel]),
            'transfer' => new AmiAction('Redirect', $id, [
                'Channel' => $target->channel,
                'Context' => $target->dialplanContext,
                'Exten' => $target->extension,
                'Priority' => $target->priority,
            ]),
            'dtmf' => new AmiAction('PlayDTMF', $id, array_filter([
                'Channel' => $target->channel,
                'Digit' => $command->arguments['digit'] ?? '',
                'Duration' => $command->arguments['duration_ms'] ?? null,
            ], static fn (mixed $value): bool => $value !== null)),
            default => throw new ContractViolation('unsupported_ami_action', 'Command is outside the frozen AMI action subset.'),
        };
    }

    /** @param array<string, mixed> $payload
     *  @return array{event_type: string, state: string}|null
     */
    private function eventMapping(string $providerType, array $payload): ?array
    {
        if ($providerType === 'DialEnd') {
            $status = $payload['DialStatus'] ?? null;
            if ($status === 'ANSWER') {
                return ['event_type' => 'call.answered', 'state' => 'answered'];
            }
            if (is_string($status) && in_array($status, ['ABORT', 'BUSY', 'CANCEL', 'CHANUNAVAIL', 'CONGESTION', 'NOANSWER'], true)) {
                return ['event_type' => 'call.failed', 'state' => 'failed'];
            }

            return null;
        }
        if ($providerType === 'Newstate') {
            $state = $payload['ChannelStateDesc'] ?? null;
            if (in_array($state, ['Ring', 'Ringing'], true)) {
                return ['event_type' => 'call.ringing', 'state' => 'ringing'];
            }
            if ($state === 'Up') {
                return ['event_type' => 'call.answered', 'state' => 'answered'];
            }

            return null;
        }

        return match ($providerType) {
            'Newchannel' => ['event_type' => 'call.created', 'state' => 'new'],
            'DialBegin' => ['event_type' => 'call.dialing', 'state' => 'dialing'],
            'BridgeEnter' => ['event_type' => 'call.bridged', 'state' => 'answered'],
            'Hold' => ['event_type' => 'call.held', 'state' => 'held'],
            'Unhold' => ['event_type' => 'call.resumed', 'state' => 'answered'],
            'Hangup' => ['event_type' => 'call.ended', 'state' => 'ended'],
            default => null,
        };
    }

    private function quarantine(string $reasonCode, ProviderEvent $event): EventMappingResult
    {
        return EventMappingResult::quarantined($reasonCode, $this->redactDiagnostic([
            'provider_event_ref' => $event->providerEventRef,
            'raw_payload' => $event->payload,
        ]));
    }
}
