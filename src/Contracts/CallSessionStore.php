<?php

declare(strict_types=1);

namespace Larena\Telephony\Contracts;

use Larena\Telephony\ValueObjects\CallSession;

interface CallSessionStore
{
    public function findByCallId(string $tenantRef, string $callId): ?CallSession;

    public function findByProviderCallHash(string $tenantRef, string $providerCallRefHash): ?CallSession;

    public function save(CallSession $session, ?int $expectedVersion): void;

    /** @return list<CallSession> */
    public function all(): array;
}
