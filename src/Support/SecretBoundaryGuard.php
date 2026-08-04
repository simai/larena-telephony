<?php

declare(strict_types=1);

namespace Larena\Telephony\Support;

use Larena\Telephony\Exceptions\ContractViolation;

final class SecretBoundaryGuard
{
    /** @var list<string> */
    private const REFERENCE_KEYS = ['secretref', 'secretrefs', 'secretleaseref'];

    /** @var list<string> */
    private const FORBIDDEN_KEY_FRAGMENTS = [
        'apikey',
        'credential',
        'password',
        'privatekey',
        'secretvalue',
        'token',
    ];

    /** @param array<string, mixed> $payload */
    public static function assertSafe(array $payload, string $path = '$'): void
    {
        foreach ($payload as $key => $value) {
            $normalized = self::normalizeKey((string) $key);
            $childPath = $path.'.'.$key;

            if (in_array($normalized, self::REFERENCE_KEYS, true)) {
                self::assertSecretReferences($value, $childPath);
                continue;
            }

            foreach (self::FORBIDDEN_KEY_FRAGMENTS as $fragment) {
                if (str_contains($normalized, $fragment)) {
                    throw new ContractViolation('raw_secret_forbidden', "Raw secret-like field is forbidden at {$childPath}.");
                }
            }

            if (is_array($value)) {
                self::assertSafe($value, $childPath);
            }
        }
    }

    public static function isSensitiveKey(string $key): bool
    {
        $normalized = self::normalizeKey($key);
        if (in_array($normalized, self::REFERENCE_KEYS, true)) {
            return false;
        }

        foreach (self::FORBIDDEN_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return in_array($normalized, ['rawpayload', 'providerpayload'], true);
    }

    private static function assertSecretReferences(mixed $value, string $path): void
    {
        $values = is_array($value) ? $value : [$value];
        foreach ($values as $reference) {
            if (!is_string($reference) || preg_match('#^secret://[a-zA-Z0-9._/-]+$#', $reference) !== 1) {
                throw new ContractViolation('invalid_secret_reference', "Secret values must be opaque secret:// references at {$path}.");
            }
        }
    }

    private static function normalizeKey(string $key): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $key));
    }

    private function __construct()
    {
    }
}
