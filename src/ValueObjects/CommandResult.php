<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\DiagnosticRedactor;
use Larena\Telephony\Support\ReasonCode;
use Larena\Telephony\Support\TelephonyContract;

final class CommandResult
{
    public readonly string $status;
    public readonly string $reasonCode;
    public readonly string $correlationId;
    public readonly ?string $providerReceiptHash;
    /** @var array<string, mixed> */
    public readonly array $diagnostic;

    /** @param array<string, mixed> $diagnostic */
    public function __construct(
        string $status,
        string $reasonCode,
        string $correlationId,
        ?string $providerReceiptHash = null,
        array $diagnostic = [],
    ) {
        if (!in_array($status, TelephonyContract::COMMAND_RESULT_STATUSES, true)) {
            throw new ContractViolation('invalid_command_result_status', 'Command result status is invalid.');
        }
        ReasonCode::assert($reasonCode);
        if ($correlationId === '') {
            throw new ContractViolation('invalid_command_result', 'Command result correlation is required.');
        }
        if ($providerReceiptHash !== null && preg_match('/^sha256:[0-9a-f]{64}$/', $providerReceiptHash) !== 1) {
            throw new ContractViolation('invalid_provider_receipt_hash', 'Provider receipt must be represented by a sha256 hash.');
        }
        $this->status = $status;
        $this->reasonCode = $reasonCode;
        $this->correlationId = $correlationId;
        $this->providerReceiptHash = $providerReceiptHash;
        $this->diagnostic = DiagnosticRedactor::redact($diagnostic);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'succeeded';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'reason_code' => $this->reasonCode,
            'correlation_id' => $this->correlationId,
            'provider_receipt_hash' => $this->providerReceiptHash,
            'diagnostic' => $this->diagnostic,
        ];
    }
}
