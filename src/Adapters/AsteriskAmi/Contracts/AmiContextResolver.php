<?php

declare(strict_types=1);

namespace Larena\Telephony\Adapters\AsteriskAmi\Contracts;

use Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiCommandTarget;
use Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiEventContext;
use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\EndpointDescriptor;
use Larena\Telephony\ValueObjects\ProviderEvent;

interface AmiContextResolver
{
    public function endpoint(string $endpointRef): ?EndpointDescriptor;

    public function commandTarget(CallCommand $command): ?AmiCommandTarget;

    public function eventContext(ProviderEvent $event): ?AmiEventContext;
}
