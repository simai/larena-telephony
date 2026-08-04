<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Support\ReasonCode;

final readonly class EndpointValidation
{
    /** @param list<string> $errors */
    private function __construct(
        public bool $valid,
        public array $errors,
    ) {
    }

    public static function valid(): self
    {
        return new self(true, []);
    }

    /** @param list<string> $errors */
    public static function invalid(array $errors): self
    {
        $errors = array_values(array_unique($errors));
        foreach ($errors as $error) {
            ReasonCode::assert($error, 'endpoint_validation_error');
        }

        return new self(false, $errors);
    }
}
