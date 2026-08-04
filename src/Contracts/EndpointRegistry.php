<?php

declare(strict_types=1);

namespace Larena\Telephony\Contracts;

use Larena\Telephony\ValueObjects\EndpointDescriptor;

interface EndpointRegistry
{
    public function register(EndpointDescriptor $endpoint): void;

    public function find(string $endpointId): ?EndpointDescriptor;

    public function has(string $endpointId): bool;

    /** @return list<EndpointDescriptor> */
    public function all(): array;
}
