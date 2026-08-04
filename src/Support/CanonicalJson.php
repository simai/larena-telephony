<?php

declare(strict_types=1);

namespace Larena\Telephony\Support;

use JsonException;

final class CanonicalJson
{
    /** @param array<string, mixed> $value */
    public static function encode(array $value): string
    {
        return json_encode(
            self::normalize($value),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        )."\n";
    }

    /** @param array<string, mixed> $value */
    public static function fingerprint(array $value): string
    {
        return 'sha256:'.hash('sha256', self::encode($value));
    }

    /** @return mixed */
    private static function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }

        return $value;
    }

    private function __construct()
    {
    }
}
