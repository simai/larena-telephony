<?php

declare(strict_types=1);

namespace Larena\Telephony\Runtime;

use Larena\Telephony\Contracts\CallSessionStore;
use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\ValueObjects\CallSession;

final class InMemoryCallSessionStore implements CallSessionStore
{
    /** @var array<string, CallSession> */
    private array $sessionsByCallId = [];

    /** @var array<string, string> */
    private array $callIdByProviderHash = [];

    public function findByCallId(string $tenantRef, string $callId): ?CallSession
    {
        return $this->sessionsByCallId[$this->callKey($tenantRef, $callId)] ?? null;
    }

    public function findByProviderCallHash(string $tenantRef, string $providerCallRefHash): ?CallSession
    {
        $providerKey = $this->providerKey($tenantRef, $providerCallRefHash);
        $callId = $this->callIdByProviderHash[$providerKey] ?? null;

        return $callId === null ? null : $this->findByCallId($tenantRef, $callId);
    }

    public function save(CallSession $session, ?int $expectedVersion): void
    {
        $callKey = $this->callKey($session->tenantRef, $session->callId);
        $providerKey = $this->providerKey($session->tenantRef, $session->providerCallRefHash);
        $existing = $this->sessionsByCallId[$callKey] ?? null;
        $providerOwner = $this->callIdByProviderHash[$providerKey] ?? null;

        if ($existing === null) {
            if ($expectedVersion !== null) {
                throw new ContractViolation('call_version_conflict', 'A new call session cannot satisfy a non-null expected version.');
            }
            if ($session->version !== 0) {
                throw new ContractViolation('invalid_initial_call_version', 'A new call session must begin at version zero.');
            }
            if ($providerOwner !== null && $providerOwner !== $session->callId) {
                throw new ContractViolation('provider_call_identity_conflict', 'Provider call identity is already bound to another logical call.');
            }
        } else {
            if ($expectedVersion === null || $existing->version !== $expectedVersion || $session->version !== $existing->version + 1) {
                throw new ContractViolation('call_version_conflict', 'Call session optimistic version check failed.');
            }
            if ($existing->providerCallRefHash !== $session->providerCallRefHash) {
                throw new ContractViolation('provider_call_identity_changed', 'A logical call cannot change provider identity.');
            }
        }

        $this->sessionsByCallId[$callKey] = $session;
        $this->callIdByProviderHash[$providerKey] = $session->callId;
    }

    public function all(): array
    {
        $sessions = $this->sessionsByCallId;
        ksort($sessions, SORT_STRING);

        return array_values($sessions);
    }

    private function callKey(string $tenantRef, string $callId): string
    {
        return hash('sha256', $tenantRef."\0".$callId);
    }

    private function providerKey(string $tenantRef, string $providerCallRefHash): string
    {
        return hash('sha256', $tenantRef."\0".$providerCallRefHash);
    }
}
