<?php

declare(strict_types=1);

namespace Larena\Telephony\Adapters\AsteriskAmi\ValueObjects;

use Larena\Telephony\Exceptions\ContractViolation;

final readonly class AmiCommandTarget
{
    public function __construct(
        public string $channel,
        public string $dialplanContext,
        public string $extension,
        public int $priority = 1,
        public int $timeoutMs = 30000,
    ) {
        if (preg_match('#^[A-Za-z0-9_-]+/[A-Za-z0-9_.@:+-]+$#', $channel) !== 1) {
            throw new ContractViolation('invalid_ami_channel', 'AMI channel must be an exact bounded channel identity, not a pattern.');
        }
        foreach ([$dialplanContext, $extension] as $value) {
            if (preg_match('/^[A-Za-z0-9_.@+-]{1,80}$/', $value) !== 1) {
                throw new ContractViolation('invalid_ami_dialplan_target', 'AMI dialplan values must be bounded literal identities.');
            }
        }
        if ($priority < 1 || $priority > 999) {
            throw new ContractViolation('invalid_ami_priority', 'AMI priority is outside the supported fixture range.');
        }
        if ($timeoutMs < 1000 || $timeoutMs > 120000) {
            throw new ContractViolation('invalid_ami_timeout', 'AMI timeout is outside the supported fixture range.');
        }
    }
}
