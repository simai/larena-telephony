<?php

declare(strict_types=1);

namespace Larena\Telephony\Adapters\AsteriskAmi\Contracts;

use Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiAction;
use Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiTransportResult;
use Larena\Telephony\ValueObjects\EndpointDescriptor;

interface AmiTransport
{
    public function send(EndpointDescriptor $endpoint, AmiAction $action): AmiTransportResult;
}
