<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use DateTimeImmutable;
use Larena\Telephony\Support\Identity;

final readonly class ProviderEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $endpointRef,
        public string $adapterKey,
        public string $providerEventRef,
        public ?string $providerCallRef,
        public DateTimeImmutable $occurredAt,
        public array $payload,
    ) {
        Identity::reference($endpointRef, 'endpoint_ref');
        Identity::namespaced($adapterKey, 'adapter_key');
        Identity::reference($providerEventRef, 'provider_event_ref');
        if ($providerCallRef !== null) {
            Identity::reference($providerCallRef, 'provider_call_ref');
        }
    }
}
