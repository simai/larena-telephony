<?php

declare(strict_types=1);

namespace Acme\LarenaTelephonyDemo;

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

final readonly class AcmeTelephonyAdapter implements TelephonyAdapter
{
    public function __construct(
        private string $adapterKey = 'acme.demo',
        private string $providerId = 'com.acme.demo',
        private string $composerPackage = 'acme/larena-telephony-demo-adapter',
        private string $contractVersion = TelephonyContract::VERSION,
        private bool $truthfulCapabilities = true,
    ) {
    }

    public function manifest(): AdapterManifest
    {
        $handlers = [
            'originate' => self::class.'::dispatch',
            'hangup' => self::class.'::dispatch',
        ];
        if (!$this->truthfulCapabilities) {
            $handlers['transfer'] = self::class.'::dispatch';
        }

        return new AdapterManifest(
            $this->adapterKey,
            new ProviderDescriptor($this->providerId, 'ACME independent fixture', 'experimental', ['fixture_protocol'], 'residency.fixture'),
            $this->composerPackage,
            '1.0.0',
            $this->contractVersion,
            'experimental',
            ['calls.hangup', 'calls.originate', 'events.ingest', 'health.probe'],
            'acme://telephony/schemas/endpoint-v1',
            self::class.'::mapEvent',
            $handlers,
            self::class.'::health',
            'acme://telephony/redaction/v1',
        );
    }

    public function validateEndpoint(EndpointDescriptor $endpoint): EndpointValidation
    {
        $errors = [];
        if ($endpoint->adapterKey !== $this->adapterKey) {
            $errors[] = 'adapter_key_mismatch';
        }
        if (!ContractVersion::supports($endpoint->contractVersion)) {
            $errors[] = 'incompatible_contract_version';
        }
        foreach (array_keys($endpoint->configuration) as $key) {
            if (!in_array($key, ['line', 'simulated_health'], true)) {
                $errors[] = 'unknown_endpoint_field:'.$key;
            }
        }
        if (!is_string($endpoint->configuration['line'] ?? null) || $endpoint->configuration['line'] === '') {
            $errors[] = 'line_required';
        }
        if (!isset($endpoint->secretRefs['connection.primary'])) {
            $errors[] = 'connection_secret_ref_required';
        }

        return $errors === [] ? EndpointValidation::valid() : EndpointValidation::invalid($errors);
    }

    public function mapEvent(ProviderEvent $event): EventMappingResult
    {
        if ($event->adapterKey !== $this->adapterKey) {
            return EventMappingResult::quarantined('adapter_key_mismatch');
        }
        $kind = $event->payload['kind'] ?? null;
        if (!is_string($kind) || $kind === '' || $event->providerCallRef === null) {
            return EventMappingResult::quarantined('malformed_provider_event', [
                'provider_event_ref' => $event->providerEventRef,
                'raw_payload' => $event->payload,
            ]);
        }
        $normalizedType = [
            'session.opened' => 'call.created',
            'session.alerting' => 'call.ringing',
            'session.connected' => 'call.answered',
            'session.closed' => 'call.ended',
        ][$kind] ?? null;
        if ($normalizedType === null) {
            return EventMappingResult::quarantined('unknown_provider_event', [
                'provider_event_ref' => $event->providerEventRef,
                'provider_payload' => $event->payload,
            ]);
        }

        $eventHash = hash('sha256', $event->providerEventRef);
        $callHash = hash('sha256', $event->providerCallRef);

        return EventMappingResult::accepted(new EventEnvelope(
            'acme.event.'.substr($eventHash, 0, 24),
            $normalizedType,
            TelephonyContract::VERSION,
            'tenant.fixture',
            $event->endpointRef,
            $event->adapterKey,
            'sha256:'.$eventHash,
            'sha256:'.$callHash,
            $event->occurredAt,
            $event->occurredAt,
            is_int($event->payload['ordinal'] ?? null) ? $event->payload['ordinal'] : null,
            'provider-event:'.$eventHash,
            'correlation.event.'.substr($eventHash, 0, 16),
            ['state' => substr($normalizedType, strlen('call.'))],
        ));
    }

    public function dispatch(CallCommand $command): CommandResult
    {
        if (!$this->manifest()->supportsCommand($command->commandType)) {
            return new CommandResult('unsupported', 'command_not_supported', $command->correlationId);
        }
        if (($command->arguments['force_uncertain'] ?? false) === true) {
            return new CommandResult('uncertain', 'provider_outcome_unknown', $command->correlationId);
        }

        return new CommandResult(
            'succeeded',
            'provider_acknowledged',
            $command->correlationId,
            'sha256:'.hash('sha256', $this->adapterKey."\0".$command->idempotencyKey),
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
