<?php

declare(strict_types=1);

namespace Larena\Telephony\Testing;

use DateTimeImmutable;
use Larena\Telephony\Contracts\AdapterConformanceFixture;
use Larena\Telephony\Contracts\TelephonyAdapter;
use Larena\Telephony\Support\TelephonyContract;
use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\EndpointDescriptor;
use Larena\Telephony\ValueObjects\ProviderEvent;

final class FakeAdapterConformanceFixture implements AdapterConformanceFixture
{
    private const ADAPTER_KEY = 'larena.fake';

    public function adapter(): TelephonyAdapter
    {
        return FakeTelephonyAdapter::create();
    }

    public function equivalentIdentityAdapter(): TelephonyAdapter
    {
        return FakeTelephonyAdapter::create('vendor.equivalent', 'com.vendor.equivalent', 'vendor/equivalent-telephony');
    }

    public function incompatibleVersionAdapter(): TelephonyAdapter
    {
        return FakeTelephonyAdapter::create(contractVersion: '2.0.0');
    }

    public function untruthfulCapabilityAdapter(): TelephonyAdapter
    {
        return FakeTelephonyAdapter::create(truthfulCapabilities: false);
    }

    public function validEndpoint(): EndpointDescriptor
    {
        return $this->endpoint(self::ADAPTER_KEY, ['extension' => '101', 'transport' => 'in_memory']);
    }

    public function invalidEndpoint(): EndpointDescriptor
    {
        return $this->endpoint(self::ADAPTER_KEY, ['extension' => '101', 'unsupported_option' => true]);
    }

    public function degradedEndpoint(): EndpointDescriptor
    {
        return $this->endpoint(self::ADAPTER_KEY, ['extension' => '101', 'simulated_health' => 'degraded']);
    }

    public function knownEventsWithDuplicateAndReorder(): array
    {
        $later = $this->event('provider-event-2', 'provider-call-1', 'ringing', '2026-01-01T10:02:00+00:00', 2);
        $earlier = $this->event('provider-event-1', 'provider-call-1', 'created', '2026-01-01T10:01:00+00:00', 1);

        return [$later, $later, $earlier];
    }

    public function unknownEvent(): ProviderEvent
    {
        return $this->event('provider-event-unknown', 'provider-call-1', 'mystery', '2026-01-01T10:03:00+00:00', 3);
    }

    public function malformedEvent(): ProviderEvent
    {
        return new ProviderEvent(
            'endpoint.fixture.main',
            self::ADAPTER_KEY,
            'provider-event-malformed',
            null,
            new DateTimeImmutable('2026-01-01T10:04:00+00:00'),
            ['sequence' => 4],
        );
    }

    public function supportedCommand(): CallCommand
    {
        return $this->command('command.fixture.originate', 'originate', 'command-idempotency-1');
    }

    public function unsupportedCommand(): CallCommand
    {
        return $this->command('command.fixture.answer', 'answer', 'command-idempotency-unsupported');
    }

    public function uncertainCommand(): CallCommand
    {
        return $this->command('command.fixture.uncertain', 'originate', 'command-idempotency-uncertain', ['force_uncertain' => true]);
    }

    public function sensitiveDiagnostic(): array
    {
        return [
            'status' => 'failed',
            'password' => 'top-secret',
            'raw_payload' => 'raw-provider-payload',
            'provider_call_ref' => 'provider-call-1',
        ];
    }

    public function composerManifestPath(): string
    {
        return dirname(__DIR__, 2).'/composer.json';
    }

    public function fingerprintMaterial(): array
    {
        return [
            'fixture' => self::class,
            'adapter_key' => self::ADAPTER_KEY,
            'event_refs' => ['provider-event-1', 'provider-event-2', 'provider-event-unknown', 'provider-event-malformed'],
            'command_types' => ['originate', 'answer'],
            'contract_version' => TelephonyContract::VERSION,
        ];
    }

    /** @param array<string, mixed> $configuration */
    private function endpoint(string $adapterKey, array $configuration): EndpointDescriptor
    {
        return new EndpointDescriptor(
            'endpoint.fixture.main',
            'tenant.fixture',
            $adapterKey,
            TelephonyContract::VERSION,
            $configuration,
            ['connection.primary' => 'secret://telephony/fixture/connection'],
            ['calls.hangup', 'calls.originate', 'events.ingest', 'health.probe'],
            ['calls.hangup', 'calls.originate', 'events.ingest', 'health.probe'],
        );
    }

    private function event(string $eventRef, string $callRef, string $type, string $occurredAt, int $sequence): ProviderEvent
    {
        return new ProviderEvent(
            'endpoint.fixture.main',
            self::ADAPTER_KEY,
            $eventRef,
            $callRef,
            new DateTimeImmutable($occurredAt),
            ['type' => $type, 'sequence' => $sequence],
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
            'endpoint.fixture.main',
            $type === 'originate' ? null : 'call.fixture.main',
            $arguments,
            $idempotencyKey,
            'correlation.'.$commandId,
            new DateTimeImmutable('2026-01-01T10:05:00+00:00'),
            'policy.fixture.commands',
            null,
        );
    }
}
