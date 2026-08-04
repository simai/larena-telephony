<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use DateTimeImmutable;
use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\ContractVersion;
use Larena\Telephony\Support\Identity;
use Larena\Telephony\Support\SecretBoundaryGuard;
use Larena\Telephony\Support\TelephonyContract;

final readonly class EventEnvelope
{
    /** @param array<string, mixed> $normalizedPayload */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $contractVersion,
        public string $tenantRef,
        public string $endpointRef,
        public string $adapterKey,
        public string $providerEventRefHash,
        public ?string $providerCallRefHash,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $receivedAt,
        public ?int $sequenceHint,
        public string $idempotencyKey,
        public string $correlationId,
        public array $normalizedPayload,
        public ?string $rawPayloadRetentionRef = null,
    ) {
        Identity::namespaced($eventId, 'event_id');
        if (!in_array($eventType, TelephonyContract::EVENT_TYPES, true)) {
            throw new ContractViolation('unknown_event_type', 'Normalized event type is unknown.');
        }
        ContractVersion::parse($contractVersion);
        Identity::reference($tenantRef, 'tenant_ref');
        Identity::reference($endpointRef, 'endpoint_ref');
        Identity::namespaced($adapterKey, 'adapter_key');
        self::assertHash($providerEventRefHash, 'provider_event_ref_hash');
        if ($providerCallRefHash !== null) {
            self::assertHash($providerCallRefHash, 'provider_call_ref_hash');
        }
        Identity::reference($idempotencyKey, 'idempotency_key');
        Identity::reference($correlationId, 'correlation_id');
        SecretBoundaryGuard::assertSafe($normalizedPayload, '$.normalized_payload');
        if ($rawPayloadRetentionRef !== null && !str_starts_with($rawPayloadRetentionRef, 'artifact://')) {
            throw new ContractViolation('invalid_raw_payload_retention_ref', 'Raw payload retention must be an artifact:// reference.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'contract_version' => $this->contractVersion,
            'tenant_ref' => $this->tenantRef,
            'endpoint_ref' => $this->endpointRef,
            'adapter_key' => $this->adapterKey,
            'provider_event_ref_hash' => $this->providerEventRefHash,
            'provider_call_ref_hash' => $this->providerCallRefHash,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'received_at' => $this->receivedAt->format(DATE_ATOM),
            'sequence_hint' => $this->sequenceHint,
            'idempotency_key' => $this->idempotencyKey,
            'correlation_id' => $this->correlationId,
            'normalized_payload' => $this->normalizedPayload,
            'raw_payload_retention_ref' => $this->rawPayloadRetentionRef,
        ];
    }

    private static function assertHash(string $value, string $field): void
    {
        if (preg_match('/^sha256:[0-9a-f]{64}$/', $value) !== 1) {
            throw new ContractViolation('invalid_hash', "{$field} must be a sha256 hash.");
        }
    }
}
