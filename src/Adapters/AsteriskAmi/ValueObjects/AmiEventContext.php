<?php

declare(strict_types=1);

namespace Larena\Telephony\Adapters\AsteriskAmi\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\Identity;
use Larena\Telephony\Support\TelephonyContract;
use Larena\Telephony\ValueObjects\CallParty;

final readonly class AmiEventContext
{
    public function __construct(
        public string $tenantRef,
        public string $direction,
        public CallParty $localParty,
        public CallParty $remoteParty,
    ) {
        Identity::reference($tenantRef, 'tenant_ref');
        if (!in_array($direction, TelephonyContract::CALL_DIRECTIONS, true)) {
            throw new ContractViolation('invalid_call_direction', 'AMI event context direction is invalid.');
        }
    }
}
