<?php

declare(strict_types=1);

namespace Larena\Telephony\Runtime;

use Larena\Telephony\Contracts\EndpointRegistry;
use Larena\Telephony\Exceptions\AdapterRegistrationRejected;
use Larena\Telephony\Registry\AdapterRegistry;
use Larena\Telephony\ValueObjects\EventIngressResult;
use Larena\Telephony\ValueObjects\ProviderEvent;

final readonly class EventIngressProcessor
{
    public function __construct(
        private AdapterRegistry $adapters,
        private EndpointRegistry $endpoints,
        private InMemoryEventInbox $inbox,
        private CallSessionProjector $projector,
    ) {
    }

    public function ingest(ProviderEvent $providerEvent): EventIngressResult
    {
        $endpoint = $this->endpoints->find($providerEvent->endpointRef);
        if ($endpoint === null) {
            return new EventIngressResult('quarantined', 'endpoint_not_registered');
        }
        if ($endpoint->adapterKey !== $providerEvent->adapterKey) {
            return new EventIngressResult('quarantined', 'endpoint_adapter_mismatch');
        }

        try {
            $adapter = $this->adapters->get($providerEvent->adapterKey);
        } catch (AdapterRegistrationRejected) {
            return new EventIngressResult('quarantined', 'adapter_not_registered');
        }

        $mapping = $adapter->mapEvent($providerEvent);
        if ($mapping->status !== 'accepted' || $mapping->envelope === null) {
            return new EventIngressResult('quarantined', $mapping->reasonCode ?? 'provider_event_quarantined');
        }
        if (!$this->inbox->accept($mapping->envelope)) {
            return new EventIngressResult('duplicate', 'event_idempotency_replay', $mapping->envelope);
        }

        $projection = $this->projector->project($mapping->envelope);
        if ($projection->status === 'quarantined') {
            return new EventIngressResult('quarantined', $projection->reasonCode, $mapping->envelope, $projection);
        }
        if ($projection->status === 'ignored') {
            return new EventIngressResult('ignored', $projection->reasonCode, $mapping->envelope, $projection);
        }

        return new EventIngressResult('accepted', $projection->reasonCode, $mapping->envelope, $projection);
    }
}
