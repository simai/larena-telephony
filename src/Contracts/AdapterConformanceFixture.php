<?php

declare(strict_types=1);

namespace Larena\Telephony\Contracts;

use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\EndpointDescriptor;
use Larena\Telephony\ValueObjects\ProviderEvent;

interface AdapterConformanceFixture
{
    public function adapter(): TelephonyAdapter;

    public function equivalentIdentityAdapter(): TelephonyAdapter;

    public function incompatibleVersionAdapter(): TelephonyAdapter;

    public function untruthfulCapabilityAdapter(): TelephonyAdapter;

    public function validEndpoint(): EndpointDescriptor;

    public function invalidEndpoint(): EndpointDescriptor;

    public function degradedEndpoint(): EndpointDescriptor;

    /** @return list<ProviderEvent> */
    public function knownEventsWithDuplicateAndReorder(): array;

    public function unknownEvent(): ProviderEvent;

    public function malformedEvent(): ProviderEvent;

    public function supportedCommand(): CallCommand;

    public function unsupportedCommand(): CallCommand;

    public function uncertainCommand(): CallCommand;

    /** @return array<string, mixed> */
    public function sensitiveDiagnostic(): array;

    public function composerManifestPath(): string;

    /** @return array<string, mixed> */
    public function fingerprintMaterial(): array;
}
