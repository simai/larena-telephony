<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\Identity;
use Larena\Telephony\Support\TelephonyContract;

final readonly class ProviderDescriptor
{
    /** @param list<string> $supportedTransportProfiles */
    public function __construct(
        public string $providerId,
        public string $displayName,
        public string $lifecycleStatus,
        public array $supportedTransportProfiles,
        public string $dataResidencyProfileRef,
    ) {
        Identity::namespaced($providerId, 'provider_id');
        if (trim($displayName) === '') {
            throw new ContractViolation('invalid_display_name', 'Provider display name cannot be empty.');
        }
        if (!in_array($lifecycleStatus, TelephonyContract::LIFECYCLE_STATUSES, true)) {
            throw new ContractViolation('invalid_lifecycle_status', 'Provider lifecycle status is invalid.');
        }
        Identity::uniqueList($supportedTransportProfiles, 'supported_transport_profiles');
        Identity::reference($dataResidencyProfileRef, 'data_residency_profile_ref');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $profiles = $this->supportedTransportProfiles;
        sort($profiles, SORT_STRING);

        return [
            'provider_id' => $this->providerId,
            'display_name' => $this->displayName,
            'lifecycle_status' => $this->lifecycleStatus,
            'supported_transport_profiles' => $profiles,
            'data_residency_profile_ref' => $this->dataResidencyProfileRef,
        ];
    }
}
