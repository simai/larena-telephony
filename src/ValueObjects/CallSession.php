<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use DateTimeImmutable;
use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\Identity;
use Larena\Telephony\Support\TelephonyContract;

final readonly class CallSession
{
    public const CONTRACT_VERSION = TelephonyContract::VERSION;

    /**
     * @param list<string> $associationRefs
     * @param list<string> $artifactRefs
     */
    public function __construct(
        public string $callId,
        public string $tenantRef,
        public string $endpointRef,
        public string $adapterKey,
        public string $providerCallRefHash,
        public string $direction,
        public string $state,
        public CallParty $localParty,
        public CallParty $remoteParty,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $answeredAt,
        public ?DateTimeImmutable $endedAt,
        public string $correlationId,
        public array $associationRefs,
        public array $artifactRefs,
        public int $version,
    ) {
        Identity::namespaced($callId, 'call_id');
        Identity::reference($tenantRef, 'tenant_ref');
        Identity::reference($endpointRef, 'endpoint_ref');
        Identity::namespaced($adapterKey, 'adapter_key');
        self::assertHash($providerCallRefHash);
        if (!in_array($direction, TelephonyContract::CALL_DIRECTIONS, true)) {
            throw new ContractViolation('invalid_call_direction', 'Call direction is invalid.');
        }
        if (!in_array($state, TelephonyContract::CALL_STATES, true)) {
            throw new ContractViolation('invalid_call_state', 'Call state is invalid.');
        }
        Identity::reference($correlationId, 'correlation_id');
        self::assertTimestampOrder($startedAt, $answeredAt, $endedAt);
        self::assertAssociationRefs($associationRefs);
        self::assertArtifactRefs($artifactRefs);
        if ($version < 0) {
            throw new ContractViolation('invalid_call_version', 'Call version cannot be negative.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $associations = $this->associationRefs;
        $artifacts = $this->artifactRefs;
        sort($associations, SORT_STRING);
        sort($artifacts, SORT_STRING);

        return [
            'call_id' => $this->callId,
            'tenant_ref' => $this->tenantRef,
            'endpoint_ref' => $this->endpointRef,
            'adapter_key' => $this->adapterKey,
            'provider_call_ref_hash' => $this->providerCallRefHash,
            'direction' => $this->direction,
            'state' => $this->state,
            'local_party' => $this->localParty->toArray(),
            'remote_party' => $this->remoteParty->toArray(),
            'started_at' => $this->startedAt->format(DATE_ATOM),
            'answered_at' => $this->answeredAt?->format(DATE_ATOM),
            'ended_at' => $this->endedAt?->format(DATE_ATOM),
            'correlation_id' => $this->correlationId,
            'association_refs' => $associations,
            'artifact_refs' => $artifacts,
            'version' => $this->version,
        ];
    }

    private static function assertHash(string $value): void
    {
        if (preg_match('/^sha256:[0-9a-f]{64}$/', $value) !== 1) {
            throw new ContractViolation('invalid_provider_call_ref_hash', 'Provider call reference must be a sha256 hash.');
        }
    }

    private static function assertTimestampOrder(
        DateTimeImmutable $startedAt,
        ?DateTimeImmutable $answeredAt,
        ?DateTimeImmutable $endedAt,
    ): void {
        if ($answeredAt !== null && $answeredAt < $startedAt) {
            throw new ContractViolation('invalid_call_timestamp_order', 'answered_at cannot precede started_at.');
        }
        if ($endedAt !== null && ($endedAt < $startedAt || ($answeredAt !== null && $endedAt < $answeredAt))) {
            throw new ContractViolation('invalid_call_timestamp_order', 'ended_at cannot precede the call timeline.');
        }
    }

    /** @param list<string> $references */
    private static function assertAssociationRefs(array $references): void
    {
        $references = Identity::uniqueList($references, 'association_refs');
        foreach ($references as $reference) {
            Identity::reference($reference, 'association_ref');
        }
    }

    /** @param list<string> $references */
    private static function assertArtifactRefs(array $references): void
    {
        $references = Identity::uniqueList($references, 'artifact_refs');
        foreach ($references as $reference) {
            if (preg_match('#^artifact://[a-zA-Z0-9._/-]+$#', $reference) !== 1) {
                throw new ContractViolation('invalid_artifact_ref', 'Artifact references must use artifact:// identifiers.');
            }
        }
    }
}
