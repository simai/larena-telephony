<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\ReasonCode;

final readonly class SessionProjectionResult
{
    private function __construct(
        public string $status,
        public ?CallSession $session,
        public string $reasonCode,
    ) {
        if (!in_array($status, ['created', 'updated', 'reconciled', 'ignored', 'quarantined'], true)) {
            throw new ContractViolation('invalid_projection_status', 'Session projection status is invalid.');
        }
        if (in_array($status, ['created', 'updated', 'reconciled'], true) !== ($session instanceof CallSession)) {
            throw new ContractViolation('invalid_projection_shape', 'A mutating projection requires a call session.');
        }
        ReasonCode::assert($reasonCode);
    }

    public static function created(CallSession $session): self
    {
        return new self('created', $session, 'call_session_created');
    }

    public static function updated(CallSession $session): self
    {
        return new self('updated', $session, 'call_session_updated');
    }

    public static function reconciled(CallSession $session, string $reasonCode): self
    {
        return new self('reconciled', $session, $reasonCode);
    }

    public static function ignored(string $reasonCode): self
    {
        return new self('ignored', null, $reasonCode);
    }

    public static function quarantined(string $reasonCode): self
    {
        return new self('quarantined', null, $reasonCode);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'reason_code' => $this->reasonCode,
            'session' => $this->session?->toArray(),
        ];
    }
}
