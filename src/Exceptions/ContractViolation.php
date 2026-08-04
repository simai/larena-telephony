<?php

declare(strict_types=1);

namespace Larena\Telephony\Exceptions;

use InvalidArgumentException;

final class ContractViolation extends InvalidArgumentException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
