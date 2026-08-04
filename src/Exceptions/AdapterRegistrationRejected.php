<?php

declare(strict_types=1);

namespace Larena\Telephony\Exceptions;

use RuntimeException;

final class AdapterRegistrationRejected extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
