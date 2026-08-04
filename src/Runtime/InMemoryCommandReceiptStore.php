<?php

declare(strict_types=1);

namespace Larena\Telephony\Runtime;

use Larena\Telephony\Contracts\CommandReceiptStore;
use Larena\Telephony\ValueObjects\CommandResult;

final class InMemoryCommandReceiptStore implements CommandReceiptStore
{
    /** @var array<string, CommandResult> */
    private array $receipts = [];

    public function find(string $tenantRef, string $idempotencyKey): ?CommandResult
    {
        return $this->receipts[$this->key($tenantRef, $idempotencyKey)] ?? null;
    }

    public function save(string $tenantRef, string $idempotencyKey, CommandResult $result): void
    {
        $this->receipts[$this->key($tenantRef, $idempotencyKey)] = $result;
    }

    private function key(string $tenantRef, string $idempotencyKey): string
    {
        return hash('sha256', $tenantRef."\0".$idempotencyKey);
    }
}
