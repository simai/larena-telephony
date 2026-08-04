<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use DateTimeImmutable;
use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\ContractVersion;
use Larena\Telephony\Support\Identity;
use Larena\Telephony\Support\SecretBoundaryGuard;
use Larena\Telephony\Support\TelephonyContract;

final readonly class CallCommand
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $commandId,
        public string $commandType,
        public string $contractVersion,
        public string $tenantRef,
        public string $actorRef,
        public string $endpointRef,
        public ?string $callRef,
        public array $arguments,
        public string $idempotencyKey,
        public string $correlationId,
        public DateTimeImmutable $requestedAt,
        public string $policyContextRef,
        public ?int $expectedCallVersion,
    ) {
        Identity::namespaced($commandId, 'command_id');
        if (!array_key_exists($commandType, TelephonyContract::COMMAND_CAPABILITIES)) {
            throw new ContractViolation('unknown_command_type', 'Command type is unknown.');
        }
        ContractVersion::parse($contractVersion);
        foreach ([$tenantRef, $actorRef, $endpointRef, $idempotencyKey, $correlationId, $policyContextRef] as $reference) {
            Identity::reference($reference, 'command_reference');
        }
        if ($callRef !== null) {
            Identity::reference($callRef, 'call_ref');
        }
        if ($expectedCallVersion !== null && $expectedCallVersion < 0) {
            throw new ContractViolation('invalid_expected_call_version', 'Expected call version cannot be negative.');
        }
        SecretBoundaryGuard::assertSafe($arguments, '$.arguments');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'command_id' => $this->commandId,
            'command_type' => $this->commandType,
            'contract_version' => $this->contractVersion,
            'tenant_ref' => $this->tenantRef,
            'actor_ref' => $this->actorRef,
            'endpoint_ref' => $this->endpointRef,
            'call_ref' => $this->callRef,
            'arguments' => $this->arguments,
            'idempotency_key' => $this->idempotencyKey,
            'correlation_id' => $this->correlationId,
            'requested_at' => $this->requestedAt->format(DATE_ATOM),
            'policy_context_ref' => $this->policyContextRef,
            'expected_call_version' => $this->expectedCallVersion,
        ];
    }
}
