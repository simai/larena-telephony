<?php

declare(strict_types=1);

namespace Acme\LarenaTelephonyDemo;

use DateTimeImmutable;
use Larena\Telephony\Contracts\AdapterConformanceFixture;
use Larena\Telephony\Contracts\TelephonyAdapter;
use Larena\Telephony\Support\TelephonyContract;
use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\EndpointDescriptor;
use Larena\Telephony\ValueObjects\ProviderEvent;

final class AcmeConformanceFixture implements AdapterConformanceFixture
{
    private const ADAPTER_KEY = 'acme.demo';

    public function adapter(): TelephonyAdapter
    {
        return new AcmeTelephonyAdapter();
    }

    public function equivalentIdentityAdapter(): TelephonyAdapter
    {
        return new AcmeTelephonyAdapter('vendor.equivalent', 'net.vendor.equivalent', 'vendor/equivalent-telephony');
    }

    public function incompatibleVersionAdapter(): TelephonyAdapter
    {
        return new AcmeTelephonyAdapter(contractVersion: '2.0.0');
    }

    public function untruthfulCapabilityAdapter(): TelephonyAdapter
    {
        return new AcmeTelephonyAdapter(truthfulCapabilities: false);
    }

    public function validEndpoint(): EndpointDescriptor
    {
        return $this->endpoint(self::ADAPTER_KEY, ['line' => 'fixture-line-1']);
    }

    public function invalidEndpoint(): EndpointDescriptor
    {
        return $this->endpoint(self::ADAPTER_KEY, ['line' => 'fixture-line-1', 'vendor_mode' => 'unsafe']);
    }

    public function degradedEndpoint(): EndpointDescriptor
    {
        return $this->endpoint(self::ADAPTER_KEY, ['line' => 'fixture-line-1', 'simulated_health' => 'degraded']);
    }

    public function knownEventsWithDuplicateAndReorder(): array
    {
        $later = $this->event('acme-event-2', 'acme-call-1', 'session.alerting', '2026-01-01T10:02:00+00:00', 2);
        $earlier = $this->event('acme-event-1', 'acme-call-1', 'session.opened', '2026-01-01T10:01:00+00:00', 1);

        return [$later, $later, $earlier];
    }

    public function unknownEvent(): ProviderEvent
    {
        return $this->event('acme-event-unknown', 'acme-call-1', 'session.mystery', '2026-01-01T10:03:00+00:00', 3);
    }

    public function malformedEvent(): ProviderEvent
    {
        return new ProviderEvent(
            'endpoint.acme.main',
            self::ADAPTER_KEY,
            'acme-event-malformed',
            null,
            new DateTimeImmutable('2026-01-01T10:04:00+00:00'),
            ['ordinal' => 4],
        );
    }

    public function supportedCommand(): CallCommand
    {
        return $this->command('command.acme.originate', 'originate', 'acme-command-1');
    }

    public function unsupportedCommand(): CallCommand
    {
        return $this->command('command.acme.answer', 'answer', 'acme-command-unsupported');
    }

    public function uncertainCommand(): CallCommand
    {
        return $this->command('command.acme.uncertain', 'originate', 'acme-command-uncertain', ['force_uncertain' => true]);
    }

    public function sensitiveDiagnostic(): array
    {
        return [
            'token' => 'top-secret',
            'provider_payload' => 'raw-provider-payload',
            'provider_event_ref' => 'acme-event-1',
        ];
    }

    public function composerManifestPath(): string
    {
        return dirname(__DIR__).'/composer.json';
    }

    public function fingerprintMaterial(): array
    {
        return [
            'fixture' => self::class,
            'adapter_key' => self::ADAPTER_KEY,
            'event_refs' => ['acme-event-1', 'acme-event-2', 'acme-event-unknown', 'acme-event-malformed'],
            'command_types' => ['originate', 'answer'],
            'contract_version' => TelephonyContract::VERSION,
        ];
    }

    /** @param array<string, mixed> $configuration */
    private function endpoint(string $adapterKey, array $configuration): EndpointDescriptor
    {
        return new EndpointDescriptor(
            'endpoint.acme.main',
            'tenant.fixture',
            $adapterKey,
            TelephonyContract::VERSION,
            $configuration,
            ['connection.primary' => 'secret://telephony/acme/connection'],
            ['calls.hangup', 'calls.originate', 'events.ingest', 'health.probe'],
            ['calls.hangup', 'calls.originate', 'events.ingest', 'health.probe'],
        );
    }

    private function event(string $eventRef, string $callRef, string $kind, string $occurredAt, int $ordinal): ProviderEvent
    {
        return new ProviderEvent(
            'endpoint.acme.main',
            self::ADAPTER_KEY,
            $eventRef,
            $callRef,
            new DateTimeImmutable($occurredAt),
            ['kind' => $kind, 'ordinal' => $ordinal],
        );
    }

    /** @param array<string, mixed> $arguments */
    private function command(string $commandId, string $type, string $idempotencyKey, array $arguments = []): CallCommand
    {
        return new CallCommand(
            $commandId,
            $type,
            TelephonyContract::VERSION,
            'tenant.fixture',
            'actor.fixture',
            'endpoint.acme.main',
            $type === 'originate' ? null : 'call.acme.main',
            $arguments,
            $idempotencyKey,
            'correlation.'.$commandId,
            new DateTimeImmutable('2026-01-01T10:05:00+00:00'),
            'policy.fixture.commands',
            null,
        );
    }
}
