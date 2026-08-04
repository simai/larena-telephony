<?php

declare(strict_types=1);

namespace Larena\Telephony\Conformance;

use Larena\Telephony\Contracts\TelephonyAdapter;
use Larena\Telephony\ValueObjects\AdapterManifest;
use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\CommandResult;
use Larena\Telephony\ValueObjects\EndpointDescriptor;
use Larena\Telephony\ValueObjects\EndpointValidation;
use Larena\Telephony\ValueObjects\EventMappingResult;
use Larena\Telephony\ValueObjects\HealthReport;
use Larena\Telephony\ValueObjects\ProviderEvent;

final class ObservingAdapter implements TelephonyAdapter
{
    public int $dispatchCount = 0;

    public function __construct(private readonly TelephonyAdapter $inner)
    {
    }

    public function manifest(): AdapterManifest
    {
        return $this->inner->manifest();
    }

    public function validateEndpoint(EndpointDescriptor $endpoint): EndpointValidation
    {
        return $this->inner->validateEndpoint($endpoint);
    }

    public function mapEvent(ProviderEvent $event): EventMappingResult
    {
        return $this->inner->mapEvent($event);
    }

    public function dispatch(CallCommand $command): CommandResult
    {
        ++$this->dispatchCount;

        return $this->inner->dispatch($command);
    }

    public function health(EndpointDescriptor $endpoint): HealthReport
    {
        return $this->inner->health($endpoint);
    }

    public function redactDiagnostic(array $diagnostic): array
    {
        return $this->inner->redactDiagnostic($diagnostic);
    }
}
