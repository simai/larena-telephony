<?php

declare(strict_types=1);

namespace Larena\Telephony\Testing;

use Larena\Telephony\Contracts\TelephonyAdapter;
use Larena\Telephony\Support\ContractVersion;
use Larena\Telephony\Support\DiagnosticRedactor;
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

final readonly class FakeTelephonyAdapter implements TelephonyAdapter
{
    public function __construct(private AdapterManifest $descriptor)
    {
    }

    public static function create(
        string $adapterKey = 'larena.fake',
        string $providerId = 'org.larena.fake',
        string $composerPackage = 'larena/telephony',
        string $contractVersion = TelephonyContract::VERSION,
        bool $truthfulCapabilities = true,
    ): self {
        $handlers = [
            'originate' => 'Larena\\Telephony\\Testing\\FakeTelephonyAdapter::dispatchOriginate',
            'hangup' => 'Larena\\Telephony\\Testing\\FakeTelephonyAdapter::dispatchHangup',
        ];
        if (!$truthfulCapabilities) {
            $handlers['transfer'] = 'Larena\\Telephony\\Testing\\FakeTelephonyAdapter::dispatchTransfer';
        }

        return new self(new AdapterManifest(
            $adapterKey,
            new ProviderDescriptor($providerId, 'Deterministic fake provider', 'experimental', ['in_memory'], 'residency.none'),
            $composerPackage,
            '1.0.0',
            $contractVersion,
            'experimental',
            ['calls.hangup', 'calls.originate', 'events.ingest', 'health.probe'],
            'larena://telephony/schemas/fake-endpoint-v1',
            'Larena\\Telephony\\Testing\\FakeTelephonyAdapter::mapEvent',
            $handlers,
            'Larena\\Telephony\\Testing\\FakeTelephonyAdapter::health',
            'larena://telephony/redaction/default-v1',
        ));
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
        $allowed = ['extension', 'simulated_health', 'transport'];
        foreach (array_keys($endpoint->configuration) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = 'unknown_endpoint_field:'.$key;
            }
        }
        if (!is_string($endpoint->configuration['extension'] ?? null) || trim((string) $endpoint->configuration['extension']) === '') {
            $errors[] = 'extension_required';
        }
        if (!isset($endpoint->secretRefs['connection.primary'])) {
            $errors[] = 'connection_secret_ref_required';
        }

        return $errors === [] ? EndpointValidation::valid() : EndpointValidation::invalid($errors);
    }

    public function mapEvent(ProviderEvent $event): EventMappingResult
    {
        if ($event->adapterKey !== $this->descriptor->adapterKey) {
            return EventMappingResult::quarantined('adapter_key_mismatch');
        }
        $providerType = $event->payload['type'] ?? null;
        if (!is_string($providerType) || $providerType === '' || $event->providerCallRef === null) {
            return EventMappingResult::quarantined('malformed_provider_event', [
                'provider_event_ref' => $event->providerEventRef,
                'raw_payload' => $event->payload,
            ]);
        }
        $eventType = [
            'created' => 'call.created',
            'ringing' => 'call.ringing',
            'answered' => 'call.answered',
            'ended' => 'call.ended',
        ][$providerType] ?? null;
        if ($eventType === null) {
            return EventMappingResult::quarantined('unknown_provider_event', [
                'provider_event_ref' => $event->providerEventRef,
                'provider_payload' => $event->payload,
            ]);
        }

        $eventHash = hash('sha256', $event->providerEventRef);
        $callHash = hash('sha256', $event->providerCallRef);

        return EventMappingResult::accepted(new EventEnvelope(
            'larena.event.'.substr($eventHash, 0, 24),
            $eventType,
            TelephonyContract::VERSION,
            'tenant.fixture',
            $event->endpointRef,
            $event->adapterKey,
            'sha256:'.$eventHash,
            'sha256:'.$callHash,
            $event->occurredAt,
            $event->occurredAt,
            is_int($event->payload['sequence'] ?? null) ? $event->payload['sequence'] : null,
            'provider-event:'.$eventHash,
            'correlation.event.'.substr($eventHash, 0, 16),
            ['state' => substr($eventType, strlen('call.'))],
        ));
    }

    public function dispatch(CallCommand $command): CommandResult
    {
        if (!$this->descriptor->supportsCommand($command->commandType)) {
            return new CommandResult('unsupported', 'command_not_supported', $command->correlationId);
        }
        if (($command->arguments['force_uncertain'] ?? false) === true) {
            return new CommandResult('uncertain', 'provider_outcome_unknown', $command->correlationId);
        }
        if (($command->arguments['force_failure'] ?? false) === true) {
            return new CommandResult('failed', 'provider_rejected', $command->correlationId);
        }

        return new CommandResult(
            'succeeded',
            'provider_acknowledged',
            $command->correlationId,
            'sha256:'.hash('sha256', $this->descriptor->adapterKey."\0".$command->idempotencyKey),
        );
    }

    public function health(EndpointDescriptor $endpoint): HealthReport
    {
        $status = $endpoint->configuration['simulated_health'] ?? 'healthy';
        if (!is_string($status) || !in_array($status, TelephonyContract::HEALTH_STATUSES, true)) {
            $status = 'unknown';
        }

        return new HealthReport($status, 'fixture_'.$status);
    }

    public function redactDiagnostic(array $diagnostic): array
    {
        return DiagnosticRedactor::redact($diagnostic);
    }
}
