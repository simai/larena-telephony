<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\ContractVersion;
use Larena\Telephony\Support\Identity;
use Larena\Telephony\Support\TelephonyContract;

final readonly class AdapterManifest
{
    /**
     * @param list<string> $capabilities
     * @param array<string, string> $commandHandlerRefs
     */
    public function __construct(
        public string $adapterKey,
        public ProviderDescriptor $provider,
        public string $composerPackage,
        public string $packageVersion,
        public string $contractVersion,
        public string $lifecycleStatus,
        public array $capabilities,
        public string $endpointSchemaRef,
        public string $eventMapperRef,
        public array $commandHandlerRefs,
        public string $healthProbeRef,
        public string $diagnosticRedactionPolicyRef,
    ) {
        Identity::namespaced($adapterKey, 'adapter_key');
        Identity::composerPackage($composerPackage);
        ContractVersion::parse($packageVersion);
        ContractVersion::parse($contractVersion);
        if (!in_array($lifecycleStatus, TelephonyContract::LIFECYCLE_STATUSES, true)) {
            throw new ContractViolation('invalid_lifecycle_status', 'Adapter lifecycle status is invalid.');
        }
        Identity::uniqueList($capabilities, 'capabilities');
        foreach ([$endpointSchemaRef, $eventMapperRef, $healthProbeRef, $diagnosticRedactionPolicyRef] as $reference) {
            Identity::reference($reference, 'manifest_reference');
        }
        foreach ($commandHandlerRefs as $command => $reference) {
            if (!is_string($command) || !array_key_exists($command, TelephonyContract::COMMAND_CAPABILITIES)) {
                throw new ContractViolation('unknown_command_handler', 'Manifest contains an unknown command handler.');
            }
            Identity::reference($reference, 'command_handler_ref');
        }
        ksort($commandHandlerRefs, SORT_STRING);
    }

    public function supportsCommand(string $command): bool
    {
        $capability = TelephonyContract::COMMAND_CAPABILITIES[$command] ?? null;

        return $capability !== null
            && array_key_exists($command, $this->commandHandlerRefs)
            && in_array($capability, $this->capabilities, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $capabilities = $this->capabilities;
        sort($capabilities, SORT_STRING);
        $handlers = $this->commandHandlerRefs;
        ksort($handlers, SORT_STRING);

        return [
            'adapter_key' => $this->adapterKey,
            'provider_id' => $this->provider->providerId,
            'composer_package' => $this->composerPackage,
            'package_version' => $this->packageVersion,
            'contract_version' => $this->contractVersion,
            'lifecycle_status' => $this->lifecycleStatus,
            'capability_manifest' => $capabilities,
            'endpoint_schema_ref' => $this->endpointSchemaRef,
            'event_mapper_ref' => $this->eventMapperRef,
            'command_handler_refs' => $handlers,
            'health_probe_ref' => $this->healthProbeRef,
            'diagnostic_redaction_policy_ref' => $this->diagnosticRedactionPolicyRef,
        ];
    }
}
