<?php

declare(strict_types=1);

namespace Larena\Telephony\Support;

use Larena\Telephony\Exceptions\ContractViolation;

final class ReasonCode
{
    public static function assert(string $value, string $field = 'reason_code'): string
    {
        if (preg_match('/^[a-z0-9]+(?:[._:-][a-z0-9]+)*$/', $value) !== 1) {
            throw new ContractViolation('invalid_reason_code', "{$field} must be a stable machine-readable code.");
        }

        return $value;
    }

    private function __construct()
    {
    }
}
