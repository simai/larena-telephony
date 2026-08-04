<?php

declare(strict_types=1);

namespace Larena\Telephony\Testing\AsteriskAmi;

use DateTimeImmutable;
use Larena\Telephony\Adapters\AsteriskAmi\AsteriskAmiAdapter;
use Larena\Telephony\Contracts\AdapterConformanceFixture;
use Larena\Telephony\Contracts\TelephonyAdapter;
use Larena\Telephony\Support\TelephonyContract;
use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\EndpointDescriptor;
use Larena\Telephony\ValueObjects\ProviderEvent;

final class AsteriskAmiConformanceFixture implements AdapterConformanceFixture
{
    private const ADAPTER_KEY = 'larena.asterisk_ami';

    public function adapter(): TelephonyAdapter
    {
        return $this->createAdapter(self::ADAPTER_KEY);
    }

    public function equivalentIdentityAdapter(): TelephonyAdapter
    {
        return $this->createAdapter('vendor.equivalent', 'net.vendor.asterisk-equivalent', 'vendor/asterisk-equivalent');
    }

    public function incompatibleVersionAdapter(): TelephonyAdapter
    {
        return $this->createAdapter(self::ADAPTER_KEY, contractVersion: '2.0.0');
    }

    public function untruthfulCapabilityAdapter(): TelephonyAdapter
    {
        return $this->createAdapter(self::ADAPTER_KEY, truthfulCapabilities: false);
    }

    public function validEndpoint(): EndpointDescriptor
    {
        return FixtureAmiContextResolver::endpointDescriptor(self::ADAPTER_KEY);
    }

    public function invalidEndpoint(): EndpointDescriptor
    {
        $valid = $this->validEndpoint();

        return new EndpointDescriptor(
            $valid->endpointId,
            $valid->tenantRef,
            $valid->adapterKey,
            $valid->contractVersion,
            [...$valid->configuration, 'host' => 'forbidden.fixture.invalid'],
            $valid->secretRefs,
            $valid->declaredCapabilities,
            $valid->effectiveCapabilities,
        );
    }

    public function degradedEndpoint(): EndpointDescriptor
    {
        return FixtureAmiContextResolver::endpointDescriptor(self::ADAPTER_KEY, 'degraded');
    }

    public function knownEventsWithDuplicateAndReorder(): array
    {
        $later = $this->event('ami-event-2', 'DialBegin', '2026-01-01T10:02:00+00:00', 2);
        $earlier = $this->event('ami-event-1', 'Newchannel', '2026-01-01T10:01:00+00:00', 1);

        return [$later, $later, $earlier];
    }

    public function unknownEvent(): ProviderEvent
    {
        return $this->event('ami-event-unknown', 'Mystery', '2026-01-01T10:03:00+00:00', 3);
    }

    public function malformedEvent(): ProviderEvent
    {
        return new ProviderEvent(
            'endpoint.asterisk.main',
            self::ADAPTER_KEY,
            'ami-event-malformed',
            null,
            new DateTimeImmutable('2026-01-01T10:04:00+00:00'),
            ['Sequence' => 4],
        );
    }

    public function supportedCommand(): CallCommand
    {
        return $this->command('command.asterisk.originate', 'originate', 'asterisk-command-1');
    }

    public function unsupportedCommand(): CallCommand
    {
        return $this->command('command.asterisk.answer', 'answer', 'asterisk-command-unsupported');
    }

    public function uncertainCommand(): CallCommand
    {
        return $this->command('command.asterisk.uncertain', 'originate', 'asterisk-command-uncertain');
    }

    public function sensitiveDiagnostic(): array
    {
        return [
            'password' => 'top-secret',
            'Channel' => 'PJSIP/sensitive-00000001',
            'Uniqueid' => 'provider-unique-id',
            'raw_payload' => 'raw-provider-payload',
        ];
    }

    public function composerManifestPath(): string
    {
        return dirname(__DIR__, 3).'/composer.json';
    }

    public function fingerprintMaterial(): array
    {
        return [
            'fixture' => self::class,
            'adapter_key' => self::ADAPTER_KEY,
            'target' => 'Asterisk 22.x AMI sanitized offline fixture',
            'event_refs' => ['ami-event-1', 'ami-event-2', 'ami-event-unknown', 'ami-event-malformed'],
            'command_types' => ['originate', 'answer'],
            'contract_version' => TelephonyContract::VERSION,
        ];
    }

    private function createAdapter(
        string $adapterKey,
        string $providerId = 'org.asterisk.ami',
        string $composerPackage = 'larena/telephony',
        string $contractVersion = TelephonyContract::VERSION,
        bool $truthfulCapabilities = true,
    ): AsteriskAmiAdapter {
        $uncertain = $this->uncertainCommand();
        $transport = new FixtureAmiTransport([
            AsteriskAmiAdapter::actionId($uncertain) => 'timeout',
        ]);

        return new AsteriskAmiAdapter(
            $transport,
            new FixtureAmiContextResolver($adapterKey),
            $adapterKey,
            $providerId,
            $composerPackage,
            $contractVersion,
            $truthfulCapabilities,
        );
    }

    private function event(string $eventRef, string $eventType, string $occurredAt, int $sequence): ProviderEvent
    {
        return new ProviderEvent(
            'endpoint.asterisk.main',
            self::ADAPTER_KEY,
            $eventRef,
            'ami-call-fixture',
            new DateTimeImmutable($occurredAt),
            [
                'Event' => $eventType,
                'Linkedid' => 'ami-call-fixture',
                'Uniqueid' => 'ami-channel-fixture',
                'Sequence' => $sequence,
            ],
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
            'endpoint.asterisk.main',
            $type === 'originate' ? null : 'call.asterisk.main',
            $arguments,
            $idempotencyKey,
            'correlation.'.$commandId,
            new DateTimeImmutable('2026-01-01T10:05:00+00:00'),
            'policy.fixture.commands',
            null,
        );
    }
}
