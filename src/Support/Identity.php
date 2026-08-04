<?php

declare(strict_types=1);

namespace Larena\Telephony\Support;

use Larena\Telephony\Exceptions\ContractViolation;

final class Identity
{
    public static function namespaced(string $value, string $field): string
    {
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)+$/', $value) !== 1) {
            throw new ContractViolation('invalid_identity', "{$field} must be a lowercase namespaced identity.");
        }

        return $value;
    }

    public static function reference(string $value, string $field): string
    {
        if ($value === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new ContractViolation('invalid_reference', "{$field} must be a non-empty bounded opaque reference.");
        }

        return $value;
    }

    public static function composerPackage(string $value): string
    {
        if (preg_match('#^[a-z0-9]([a-z0-9._-]*[a-z0-9])?/[a-z0-9]([a-z0-9._-]*[a-z0-9])?$#', $value) !== 1) {
            throw new ContractViolation('invalid_composer_package', 'Composer package identity is invalid.');
        }

        return $value;
    }

    /** @param list<string> $values
     *  @return list<string>
     */
    public static function uniqueList(array $values, string $field): array
    {
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new ContractViolation('invalid_list_value', "{$field} must contain non-empty strings.");
            }
        }
        if (count($values) !== count(array_unique($values))) {
            throw new ContractViolation('duplicate_list_value', "{$field} must contain unique values.");
        }

        sort($values, SORT_STRING);

        return $values;
    }

    private function __construct()
    {
    }
}
