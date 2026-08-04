<?php

declare(strict_types=1);

namespace Larena\Telephony\Adapters\AsteriskAmi\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\ReasonCode;

final readonly class AmiTransportResult
{
    public function __construct(
        public string $status,
        public string $reasonCode,
        public ?string $actionId = null,
        public ?string $responseReceiptHash = null,
    ) {
        if (!in_array($status, ['acknowledged', 'rejected', 'timeout', 'disconnected', 'uncorrelated'], true)) {
            throw new ContractViolation('invalid_ami_transport_status', 'AMI transport result status is invalid.');
        }
        ReasonCode::assert($reasonCode);
        if ($responseReceiptHash !== null && preg_match('/^sha256:[0-9a-f]{64}$/', $responseReceiptHash) !== 1) {
            throw new ContractViolation('invalid_ami_response_hash', 'AMI response receipt must be represented by a sha256 hash.');
        }
        if ($status === 'acknowledged' && ($actionId === null || $responseReceiptHash === null)) {
            throw new ContractViolation('invalid_ami_acknowledgement', 'AMI acknowledgement requires correlation and a hashed receipt.');
        }
    }
}
