<?php

declare(strict_types=1);

namespace Larena\Telephony\Conformance;

use Larena\Telephony\Contracts\AdapterConformanceFixture;
use Larena\Telephony\Contracts\CommandAuthorizer;
use Larena\Telephony\Exceptions\AdapterRegistrationRejected;
use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Registry\AdapterRegistry;
use Larena\Telephony\Runtime\GuardedCommandDispatcher;
use Larena\Telephony\Runtime\InMemoryCommandReceiptStore;
use Larena\Telephony\Runtime\InMemoryEventInbox;
use Larena\Telephony\Support\CanonicalJson;
use Larena\Telephony\Support\TelephonyContract;
use Larena\Telephony\ValueObjects\AuthorizationDecision;
use Larena\Telephony\ValueObjects\CompatibilityReceipt;
use Larena\Telephony\ValueObjects\ConformanceCaseResult;
use Larena\Telephony\ValueObjects\EndpointDescriptor;
use Larena\Telephony\ValueObjects\EventEnvelope;
use Larena\Telephony\ValueObjects\ProviderEvent;
use RuntimeException;
use Throwable;

final readonly class AdapterConformanceSuite
{
    /** @var list<string> */
    public const REQUIRED_CASES = [
        'unique_registration',
        'contract_version_compatibility',
        'capability_truthfulness',
        'endpoint_schema_validation',
        'duplicate_and_reordered_event_idempotency',
        'unknown_and_malformed_event_quarantine',
        'command_access_before_dispatch',
        'command_idempotency',
        'unsupported_command_denial',
        'uncertain_provider_result_not_success',
        'health_and_degraded_state',
        'secret_and_provider_payload_redaction',
        'no_provider_name_branching_in_domain_runtime',
        'no_private_package_dependency',
    ];

    public function __construct(private string $packageRoot)
    {
    }

    public function run(AdapterConformanceFixture $fixture): CompatibilityReceipt
    {
        $adapter = $fixture->adapter();
        $results = [];
        $results[] = $this->case('unique_registration', function () use ($adapter): array {
            $registry = new AdapterRegistry();
            $registry->register($adapter);
            $rejected = null;
            try {
                $registry->register($adapter);
            } catch (AdapterRegistrationRejected $exception) {
                $rejected = $exception->reasonCode;
            }
            $this->assert($rejected === 'duplicate_adapter_key', 'Duplicate adapter key was not rejected.');

            return ['registered_key' => $adapter->manifest()->adapterKey, 'duplicate_reason' => $rejected];
        });
        $results[] = $this->case('contract_version_compatibility', function () use ($fixture): array {
            $reason = $this->registrationRejection($fixture->incompatibleVersionAdapter());
            $this->assert($reason === 'incompatible_contract_version', 'Incompatible contract version was accepted.');

            return ['reason' => $reason];
        });
        $results[] = $this->case('capability_truthfulness', function () use ($fixture): array {
            $reason = $this->registrationRejection($fixture->untruthfulCapabilityAdapter());
            $this->assert($reason === 'capability_handler_mismatch', 'Capability/handler mismatch was accepted.');

            return ['reason' => $reason];
        });
        $results[] = $this->case('endpoint_schema_validation', function () use ($adapter, $fixture): array {
            $valid = $adapter->validateEndpoint($fixture->validEndpoint());
            $invalid = $adapter->validateEndpoint($fixture->invalidEndpoint());
            $this->assert($valid->valid, 'Valid endpoint was rejected.');
            $this->assert(!$invalid->valid && $invalid->errors !== [], 'Invalid endpoint was accepted.');

            return ['valid' => $valid->valid, 'invalid_errors' => $invalid->errors];
        });
        $results[] = $this->case('duplicate_and_reordered_event_idempotency', function () use ($adapter, $fixture): array {
            $inbox = new InMemoryEventInbox();
            $accepted = [];
            foreach ($fixture->knownEventsWithDuplicateAndReorder() as $providerEvent) {
                $mapping = $adapter->mapEvent($providerEvent);
                if ($mapping->status !== 'accepted' || !$mapping->envelope instanceof EventEnvelope) {
                    throw new RuntimeException('Known event was not normalized.');
                }
                $accepted[] = $inbox->accept($mapping->envelope);
            }
            $ordered = $inbox->ordered();
            $this->assert($accepted === [true, false, true], 'Duplicate/reordered event acceptance differs.');
            $this->assert(count($ordered) === 2, 'Duplicate event created a second normalized identity.');
            $this->assert($ordered[0]->occurredAt <= $ordered[1]->occurredAt, 'Reordered events were not deterministically ordered.');

            return [
                'acceptance' => $accepted,
                'ordered_event_types' => array_map(static fn ($event): string => $event->eventType, $ordered),
            ];
        });
        $results[] = $this->case('unknown_and_malformed_event_quarantine', function () use ($adapter, $fixture): array {
            $unknown = $adapter->mapEvent($fixture->unknownEvent());
            $malformed = $adapter->mapEvent($fixture->malformedEvent());
            $this->assert($unknown->status === 'quarantined' && $unknown->reasonCode === 'unknown_provider_event', 'Unknown event was not quarantined.');
            $this->assert($malformed->status === 'quarantined' && $malformed->reasonCode === 'malformed_provider_event', 'Malformed event was not quarantined.');

            return ['unknown' => $unknown->reasonCode, 'malformed' => $malformed->reasonCode];
        });
        $results[] = $this->case('command_access_before_dispatch', function () use ($adapter, $fixture): array {
            $observing = new ObservingAdapter($adapter);
            $dispatcher = $this->dispatcher($observing, false);
            $result = $dispatcher->dispatch($adapter->manifest()->adapterKey, $fixture->supportedCommand());
            $this->assert($result->status === 'denied', 'Denied command did not stay denied.');
            $this->assert($observing->dispatchCount === 0, 'Adapter was called before Access allowed the command.');

            return ['status' => $result->status, 'dispatch_count' => $observing->dispatchCount];
        });
        $results[] = $this->case('command_idempotency', function () use ($adapter, $fixture): array {
            $observing = new ObservingAdapter($adapter);
            $dispatcher = $this->dispatcher($observing, true);
            $command = $fixture->supportedCommand();
            $first = $dispatcher->dispatch($adapter->manifest()->adapterKey, $command);
            $second = $dispatcher->dispatch($adapter->manifest()->adapterKey, $command);
            $this->assert($observing->dispatchCount === 1, 'Repeated idempotency key dispatched twice.');
            $this->assert($first->toArray() === $second->toArray(), 'Repeated command did not return the same normalized receipt.');

            return ['status' => $first->status, 'dispatch_count' => $observing->dispatchCount];
        });
        $results[] = $this->case('unsupported_command_denial', function () use ($adapter, $fixture): array {
            $observing = new ObservingAdapter($adapter);
            $dispatcher = $this->dispatcher($observing, true);
            $result = $dispatcher->dispatch($adapter->manifest()->adapterKey, $fixture->unsupportedCommand());
            $this->assert($result->status === 'unsupported', 'Unsupported command was not denied.');
            $this->assert($observing->dispatchCount === 0, 'Unsupported command reached the provider adapter.');

            return ['status' => $result->status, 'dispatch_count' => $observing->dispatchCount];
        });
        $results[] = $this->case('uncertain_provider_result_not_success', function () use ($adapter, $fixture): array {
            $observing = new ObservingAdapter($adapter);
            $dispatcher = $this->dispatcher($observing, true);
            $result = $dispatcher->dispatch($adapter->manifest()->adapterKey, $fixture->uncertainCommand());
            $this->assert($result->status === 'uncertain' && !$result->isSuccess(), 'Uncertain provider outcome was labeled success.');

            return ['status' => $result->status, 'is_success' => $result->isSuccess()];
        });
        $results[] = $this->case('health_and_degraded_state', function () use ($adapter, $fixture): array {
            $healthy = $adapter->health($fixture->validEndpoint());
            $degraded = $adapter->health($fixture->degradedEndpoint());
            $this->assert($healthy->status === 'healthy', 'Valid endpoint is not healthy in deterministic fixture.');
            $this->assert($degraded->status === 'degraded', 'Degraded endpoint state was hidden.');

            return ['healthy' => $healthy->status, 'degraded' => $degraded->status];
        });
        $results[] = $this->case('secret_and_provider_payload_redaction', function () use ($adapter, $fixture): array {
            $secretRejected = false;
            $endpoint = $fixture->validEndpoint();
            try {
                new EndpointDescriptor(
                    $endpoint->endpointId,
                    $endpoint->tenantRef,
                    $endpoint->adapterKey,
                    $endpoint->contractVersion,
                    [...$endpoint->configuration, 'password' => 'top-secret'],
                    $endpoint->secretRefs,
                    $endpoint->declaredCapabilities,
                    $endpoint->effectiveCapabilities,
                );
            } catch (ContractViolation $exception) {
                $secretRejected = $exception->reasonCode === 'raw_secret_forbidden';
            }
            $redacted = $adapter->redactDiagnostic($fixture->sensitiveDiagnostic());
            $encoded = CanonicalJson::encode($redacted);
            $this->assert($secretRejected, 'Raw endpoint secret was accepted.');
            $this->assert(!str_contains($encoded, 'top-secret') && !str_contains($encoded, 'raw-provider-payload'), 'Diagnostic redaction leaked a secret or provider payload.');
            $this->assert(str_contains($encoded, '[REDACTED]'), 'Diagnostic redaction did not mark protected values.');

            return ['secret_rejected' => $secretRejected, 'redacted_fingerprint' => CanonicalJson::fingerprint($redacted)];
        });
        $results[] = $this->case('no_provider_name_branching_in_domain_runtime', function () use ($adapter, $fixture): array {
            $equivalent = $fixture->equivalentIdentityAdapter();
            $left = $this->behaviorSignature($adapter, $fixture->validEndpoint(), $fixture->knownEventsWithDuplicateAndReorder()[0], $fixture->supportedCommand());
            $rightEndpoint = $this->withAdapterKey($fixture->validEndpoint(), $equivalent->manifest()->adapterKey);
            $rightEvent = $this->withAdapterKeyOnEvent($fixture->knownEventsWithDuplicateAndReorder()[0], $equivalent->manifest()->adapterKey);
            $right = $this->behaviorSignature($equivalent, $rightEndpoint, $rightEvent, $fixture->supportedCommand());
            $this->assert($left === $right, 'Equivalent provider identity changed normalized behavior.');

            return ['signature' => $left, 'identity_pair' => [$adapter->manifest()->adapterKey, $equivalent->manifest()->adapterKey]];
        });
        $results[] = $this->case('no_private_package_dependency', function () use ($fixture): array {
            $boundary = ComposerDependencyBoundary::inspect($fixture->composerManifestPath());
            $this->assert($boundary['status'] === 'passed', 'Adapter composer manifest crosses the public dependency boundary.');

            return $boundary;
        });

        $manifest = $adapter->manifest();
        $failed = array_filter($results, static fn (ConformanceCaseResult $result): bool => $result->status !== 'passed');
        $fixtureFingerprint = CanonicalJson::fingerprint([
            'fixture' => $fixture->fingerprintMaterial(),
            'composer_manifest_sha256' => hash_file('sha256', $fixture->composerManifestPath()),
        ]);
        $lockPath = $this->packageRoot.'/composer.lock';
        $environmentFingerprint = CanonicalJson::fingerprint([
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'os_family' => PHP_OS_FAMILY,
            'suite_version' => TelephonyContract::SUITE_VERSION,
            'package_lock_sha256' => is_file($lockPath) ? hash_file('sha256', $lockPath) : null,
        ]);

        return new CompatibilityReceipt(
            $manifest->adapterKey,
            $manifest->composerPackage,
            $manifest->packageVersion,
            $manifest->contractVersion,
            TelephonyContract::SUITE_VERSION,
            $fixtureFingerprint,
            $environmentFingerprint,
            $results,
            $failed === [] ? 'conformant' : 'rejected',
            [
                'Conformance proves compatibility with the tested public contract and deterministic fixtures only.',
                'It is not security certification, PBX connectivity, carrier approval, runtime acceptance or production eligibility.',
            ],
        );
    }

    /** @param callable(): array<string, mixed> $callback */
    private function case(string $caseId, callable $callback): ConformanceCaseResult
    {
        try {
            return new ConformanceCaseResult($caseId, 'passed', $callback());
        } catch (Throwable $exception) {
            return new ConformanceCaseResult(
                $caseId,
                'failed',
                ['exception_fingerprint' => 'sha256:'.hash('sha256', $exception::class."\0".$exception->getMessage())],
                $exception::class.': conformance_case_failed',
            );
        }
    }

    private function registrationRejection(\Larena\Telephony\Contracts\TelephonyAdapter $adapter): ?string
    {
        try {
            (new AdapterRegistry())->register($adapter);
        } catch (AdapterRegistrationRejected $exception) {
            return $exception->reasonCode;
        }

        return null;
    }

    private function dispatcher(ObservingAdapter $adapter, bool $allow): GuardedCommandDispatcher
    {
        $registry = new AdapterRegistry();
        $registry->register($adapter);
        $authorizer = new class($allow) implements CommandAuthorizer {
            public function __construct(private readonly bool $allow)
            {
            }

            public function authorize(\Larena\Telephony\ValueObjects\CallCommand $command): AuthorizationDecision
            {
                return $this->allow ? AuthorizationDecision::allow() : AuthorizationDecision::deny();
            }
        };

        return new GuardedCommandDispatcher($registry, $authorizer, new InMemoryCommandReceiptStore());
    }

    /** @return array<string, mixed> */
    private function behaviorSignature(
        \Larena\Telephony\Contracts\TelephonyAdapter $adapter,
        EndpointDescriptor $endpoint,
        ProviderEvent $event,
        \Larena\Telephony\ValueObjects\CallCommand $command,
    ): array {
        $endpointValidation = $adapter->validateEndpoint($endpoint);
        $mapping = $adapter->mapEvent($event);
        $commandResult = $adapter->dispatch($command);

        return [
            'endpoint_valid' => $endpointValidation->valid,
            'event_status' => $mapping->status,
            'event_type' => $mapping->envelope?->eventType,
            'command_status' => $commandResult->status,
            'command_reason' => $commandResult->reasonCode,
            'health_status' => $adapter->health($endpoint)->status,
        ];
    }

    private function withAdapterKey(EndpointDescriptor $endpoint, string $adapterKey): EndpointDescriptor
    {
        return new EndpointDescriptor(
            $endpoint->endpointId,
            $endpoint->tenantRef,
            $adapterKey,
            $endpoint->contractVersion,
            $endpoint->configuration,
            $endpoint->secretRefs,
            $endpoint->declaredCapabilities,
            $endpoint->effectiveCapabilities,
            $endpoint->lifecycleStatus,
            $endpoint->healthStatus,
        );
    }

    private function withAdapterKeyOnEvent(ProviderEvent $event, string $adapterKey): ProviderEvent
    {
        return new ProviderEvent(
            $event->endpointRef,
            $adapterKey,
            $event->providerEventRef,
            $event->providerCallRef,
            $event->occurredAt,
            $event->payload,
        );
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
