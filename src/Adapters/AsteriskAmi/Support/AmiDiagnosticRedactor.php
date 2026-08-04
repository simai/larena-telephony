<?php

declare(strict_types=1);

namespace Larena\Telephony\Adapters\AsteriskAmi\Support;

use Larena\Telephony\Support\DiagnosticRedactor;

final class AmiDiagnosticRedactor
{
    /** @param array<string, mixed> $diagnostic
     *  @return array<string, mixed>
     */
    public static function redact(array $diagnostic): array
    {
        $protected = ['channel', 'uniqueid', 'linkedid', 'calleridnum', 'connectedlinenum', 'exten'];
        $result = [];
        foreach ($diagnostic as $key => $value) {
            $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', (string) $key));
            if (in_array($normalized, $protected, true)) {
                $result[$key] = is_scalar($value) ? 'sha256:'.hash('sha256', (string) $value) : '[REDACTED]';
                continue;
            }
            if ($normalized === 'rawpayload' || $normalized === 'providerpayload') {
                $result[$key] = '[REDACTED]';
                continue;
            }
            $result[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return DiagnosticRedactor::redact($result);
    }

    private function __construct()
    {
    }
}
