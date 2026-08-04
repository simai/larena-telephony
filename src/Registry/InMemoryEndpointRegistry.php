<?php

declare(strict_types=1);

namespace Larena\Telephony\Registry;

use Larena\Telephony\Contracts\EndpointRegistry;
use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\ValueObjects\EndpointDescriptor;

final class InMemoryEndpointRegistry implements EndpointRegistry
{
    /** @var array<string, EndpointDescriptor> */
    private array $endpoints = [];

    public function register(EndpointDescriptor $endpoint): void
    {
        if (isset($this->endpoints[$endpoint->endpointId])) {
            throw new ContractViolation('duplicate_endpoint_id', "Endpoint {$endpoint->endpointId} is already registered.");
        }

        $this->endpoints[$endpoint->endpointId] = $endpoint;
    }

    public function find(string $endpointId): ?EndpointDescriptor
    {
        return $this->endpoints[$endpointId] ?? null;
    }

    public function has(string $endpointId): bool
    {
        return isset($this->endpoints[$endpointId]);
    }

    public function all(): array
    {
        $endpoints = $this->endpoints;
        ksort($endpoints, SORT_STRING);

        return array_values($endpoints);
    }
}
