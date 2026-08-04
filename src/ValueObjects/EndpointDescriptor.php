<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\ContractVersion;
use Larena\Telephony\Support\Identity;
use Larena\Telephony\Support\SecretBoundaryGuard;
use Larena\Telephony\Support\TelephonyContract;

final readonly class EndpointDescriptor
{
    /**
     * @param array<string, mixed> $configuration
     * @param array<string, string> $secretRefs
     * @param list<string> $declaredCapabilities
     * @param list<string> $effectiveCapabilities
     */
    public function __construct(
        public string $endpointId,
        public string $tenantRef,
        public string $adapterKey,
        public string $contractVersion,
        public array $configuration,
        public array $secretRefs,
        public array $declaredCapabilities,
        public array $effectiveCapabilities,
        public string $lifecycleStatus = 'active',
        public string $healthStatus = 'unknown',
    ) {
        Identity::namespaced($endpointId, 'endpoint_id');
        Identity::reference($tenantRef, 'tenant_ref');
        Identity::namespaced($adapterKey, 'adapter_key');
        ContractVersion::parse($contractVersion);
        SecretBoundaryGuard::assertSafe($configuration, '$.configuration');
        foreach ($secretRefs as $name => $reference) {
            Identity::namespaced((string) $name, 'secret_ref_name');
            SecretBoundaryGuard::assertSafe(['secret_ref' => $reference], '$.secret_refs');
        }
        $declared = Identity::uniqueList($declaredCapabilities, 'declared_capabilities');
        $effective = Identity::uniqueList($effectiveCapabilities, 'effective_capabilities');
        foreach ($effective as $capability) {
            if (!in_array($capability, $declared, true)) {
                throw new ContractViolation('effective_capability_escalation', 'Effective capabilities must be a subset of declared capabilities.');
            }
        }
        if (!in_array($lifecycleStatus, ['active', 'disabled', 'retired'], true)) {
            throw new ContractViolation('invalid_endpoint_lifecycle', 'Endpoint lifecycle status is invalid.');
        }
        if (!in_array($healthStatus, TelephonyContract::HEALTH_STATUSES, true)) {
            throw new ContractViolation('invalid_health_status', 'Endpoint health status is invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $secretRefs = $this->secretRefs;
        ksort($secretRefs, SORT_STRING);
        $declared = $this->declaredCapabilities;
        $effective = $this->effectiveCapabilities;
        sort($declared, SORT_STRING);
        sort($effective, SORT_STRING);

        return [
            'endpoint_id' => $this->endpointId,
            'tenant_ref' => $this->tenantRef,
            'adapter_key' => $this->adapterKey,
            'contract_version' => $this->contractVersion,
            'configuration' => $this->configuration,
            'secret_refs' => $secretRefs,
            'declared_capabilities' => $declared,
            'effective_capabilities' => $effective,
            'lifecycle_status' => $this->lifecycleStatus,
            'health_status' => $this->healthStatus,
        ];
    }
}
