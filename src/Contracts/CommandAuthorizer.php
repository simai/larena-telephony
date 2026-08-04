<?php

declare(strict_types=1);

namespace Larena\Telephony\Contracts;

use Larena\Telephony\ValueObjects\AuthorizationDecision;
use Larena\Telephony\ValueObjects\CallCommand;

interface CommandAuthorizer
{
    public function authorize(CallCommand $command): AuthorizationDecision;
}
