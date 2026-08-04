<?php

declare(strict_types=1);

namespace Larena\Telephony\Contracts;

use Larena\Telephony\ValueObjects\AdapterManifest;
use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\CommandResult;
use Larena\Telephony\ValueObjects\EndpointDescriptor;
use Larena\Telephony\ValueObjects\EndpointValidation;
use Larena\Telephony\ValueObjects\EventMappingResult;
use Larena\Telephony\ValueObjects\HealthReport;
use Larena\Telephony\ValueObjects\ProviderEvent;

interface TelephonyAdapter
{
    public function manifest(): AdapterManifest;

    public function validateEndpoint(EndpointDescriptor $endpoint): EndpointValidation;

    public function mapEvent(ProviderEvent $event): EventMappingResult;

    public function dispatch(CallCommand $command): CommandResult;

    public function health(EndpointDescriptor $endpoint): HealthReport;

    /** @param array<string, mixed> $diagnostic
     *  @return array<string, mixed>
     */
    public function redactDiagnostic(array $diagnostic): array;
}
