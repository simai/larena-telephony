<?php

declare(strict_types=1);

namespace Larena\Telephony\Contracts;

use Larena\Telephony\ValueObjects\CommandResult;

interface CommandReceiptStore
{
    public function find(string $tenantRef, string $idempotencyKey): ?CommandResult;

    public function save(string $tenantRef, string $idempotencyKey, CommandResult $result): void;
}
