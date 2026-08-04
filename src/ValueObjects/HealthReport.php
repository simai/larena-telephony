<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\DiagnosticRedactor;
use Larena\Telephony\Support\ReasonCode;
use Larena\Telephony\Support\TelephonyContract;

final class HealthReport
{
    public readonly string $status;
    public readonly string $reasonCode;
    /** @var array<string, mixed> */
    public readonly array $diagnostic;

    /** @param array<string, mixed> $diagnostic */
    public function __construct(
        string $status,
        string $reasonCode,
        array $diagnostic = [],
    ) {
        if (!in_array($status, TelephonyContract::HEALTH_STATUSES, true)) {
            throw new ContractViolation('invalid_health_status', 'Health status is invalid.');
        }
        ReasonCode::assert($reasonCode);
        $this->status = $status;
        $this->reasonCode = $reasonCode;
        $this->diagnostic = DiagnosticRedactor::redact($diagnostic);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'reason_code' => $this->reasonCode,
            'diagnostic' => $this->diagnostic,
        ];
    }
}
