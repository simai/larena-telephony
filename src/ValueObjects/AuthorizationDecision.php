<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Support\ReasonCode;

final readonly class AuthorizationDecision
{
    private function __construct(
        public bool $allowed,
        public string $reasonCode,
    ) {
        ReasonCode::assert($reasonCode);
    }

    public static function allow(string $reasonCode = 'access_allowed'): self
    {
        return new self(true, $reasonCode);
    }

    public static function deny(string $reasonCode = 'access_denied'): self
    {
        return new self(false, $reasonCode);
    }
}
