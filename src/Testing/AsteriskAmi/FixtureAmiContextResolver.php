<?php

declare(strict_types=1);

namespace Larena\Telephony\Testing\AsteriskAmi;

use Larena\Telephony\Adapters\AsteriskAmi\Contracts\AmiContextResolver;
use Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiCommandTarget;
use Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiEventContext;
use Larena\Telephony\Support\TelephonyContract;
use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\CallParty;
use Larena\Telephony\ValueObjects\EndpointDescriptor;
use Larena\Telephony\ValueObjects\ProviderEvent;

final readonly class FixtureAmiContextResolver implements AmiContextResolver
{
    public function __construct(
        private string $adapterKey = 'larena.asterisk_ami',
        private string $channel = 'PJSIP/fixture-00000001',
    ) {
    }

    public function endpoint(string $endpointRef): ?EndpointDescriptor
    {
        if ($endpointRef !== 'endpoint.asterisk.main') {
            return null;
        }

        return self::endpointDescriptor($this->adapterKey);
    }

    public function commandTarget(CallCommand $command): ?AmiCommandTarget
    {
        if ($command->endpointRef !== 'endpoint.asterisk.main') {
            return null;
        }

        return new AmiCommandTarget($this->channel, 'larena-fixture', 'fixture-target', 1, 30000);
    }

    public function eventContext(ProviderEvent $event): ?AmiEventContext
    {
        if ($event->endpointRef !== 'endpoint.asterisk.main') {
            return null;
        }

        return new AmiEventContext(
            'tenant.fixture',
            'outbound',
            new CallParty('extension.sha256-local-fixture', 'extension'),
            new CallParty('external.sha256-remote-fixture', 'external'),
        );
    }

    public static function endpointDescriptor(string $adapterKey = 'larena.asterisk_ami', string $health = 'healthy'): EndpointDescriptor
    {
        return new EndpointDescriptor(
            'endpoint.asterisk.main',
            'tenant.fixture',
            $adapterKey,
            TelephonyContract::VERSION,
            [
                'ami_series' => '22.x',
                'routing_profile_ref' => 'routing.asterisk.fixture',
                'simulated_health' => $health,
            ],
            ['connection.primary' => 'secret://telephony/asterisk/fixture-connection'],
            ['calls.dtmf', 'calls.hangup', 'calls.originate', 'calls.transfer', 'events.ingest', 'health.probe'],
            ['calls.dtmf', 'calls.hangup', 'calls.originate', 'calls.transfer', 'events.ingest', 'health.probe'],
        );
    }
}
