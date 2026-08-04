<?php

declare(strict_types=1);

namespace Larena\Telephony\Support;

use Larena\Telephony\Exceptions\ContractViolation;

final class ContractVersion
{
    /** @return array{major: int, minor: int, patch: int} */
    public static function parse(string $version): array
    {
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-[0-9A-Za-z.-]+)?$/', $version, $matches) !== 1) {
            throw new ContractViolation('invalid_contract_version', 'Contract version must be semantic x.y.z.');
        }

        return [
            'major' => (int) $matches[1],
            'minor' => (int) $matches[2],
            'patch' => (int) $matches[3],
        ];
    }

    public static function supports(string $version): bool
    {
        return self::parse($version)['major'] === 1;
    }

    private function __construct()
    {
    }
}
