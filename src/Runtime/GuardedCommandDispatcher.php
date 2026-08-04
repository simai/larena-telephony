<?php

declare(strict_types=1);

namespace Larena\Telephony\Runtime;

use Larena\Telephony\Contracts\CommandAuthorizer;
use Larena\Telephony\Contracts\CommandReceiptStore;
use Larena\Telephony\Registry\AdapterRegistry;
use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\CommandResult;

final readonly class GuardedCommandDispatcher
{
    public function __construct(
        private AdapterRegistry $registry,
        private CommandAuthorizer $authorizer,
        private CommandReceiptStore $receipts,
    ) {
    }

    public function dispatch(string $adapterKey, CallCommand $command): CommandResult
    {
        $decision = $this->authorizer->authorize($command);
        if (!$decision->allowed) {
            return new CommandResult('denied', $decision->reasonCode, $command->correlationId);
        }

        $existing = $this->receipts->find($command->tenantRef, $command->idempotencyKey);
        if ($existing instanceof CommandResult) {
            return $existing;
        }

        $adapter = $this->registry->get($adapterKey);
        if (!$adapter->manifest()->supportsCommand($command->commandType)) {
            $result = new CommandResult('unsupported', 'command_not_declared_by_adapter', $command->correlationId);
            $this->receipts->save($command->tenantRef, $command->idempotencyKey, $result);

            return $result;
        }

        $result = $adapter->dispatch($command);
        $this->receipts->save($command->tenantRef, $command->idempotencyKey, $result);

        return $result;
    }
}
