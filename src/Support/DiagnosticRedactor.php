<?php

declare(strict_types=1);

namespace Larena\Telephony\Support;

final class DiagnosticRedactor
{
    /** @param array<string, mixed> $diagnostic
     *  @return array<string, mixed>
     */
    public static function redact(array $diagnostic): array
    {
        $result = [];
        foreach ($diagnostic as $key => $value) {
            if (SecretBoundaryGuard::isSensitiveKey((string) $key)) {
                $result[$key] = '[REDACTED]';
                continue;
            }

            if (in_array(strtolower((string) $key), ['provider_call_ref', 'provider_event_ref', 'phone', 'number'], true)) {
                $result[$key] = is_scalar($value) ? 'sha256:'.hash('sha256', (string) $value) : '[REDACTED]';
                continue;
            }

            $result[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $result;
    }

    private function __construct()
    {
    }
}
