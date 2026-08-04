<?php

declare(strict_types=1);

namespace Larena\Telephony\Testing\AsteriskAmi;

use DateTimeImmutable;
use Larena\Telephony\Adapters\AsteriskAmi\AsteriskAmiAdapter;
use Larena\Telephony\Conformance\AdapterConformanceSuite;
use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Registry\AdapterRegistry;
use Larena\Telephony\Registry\InMemoryEndpointRegistry;
use Larena\Telephony\Runtime\CallSessionProjector;
use Larena\Telephony\Runtime\CallTransitionPolicy;
use Larena\Telephony\Runtime\EventIngressProcessor;
use Larena\Telephony\Runtime\InMemoryCallSessionStore;
use Larena\Telephony\Runtime\InMemoryEventInbox;
use Larena\Telephony\Support\CanonicalJson;
use Larena\Telephony\Support\TelephonyContract;
use Larena\Telephony\ValueObjects\CallCommand;
use Larena\Telephony\ValueObjects\ProviderEvent;

final class AsteriskAmiCoreTestSuite
{
    private int $checks = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var array<string, mixed> */
    private array $observations = [];

    public function __construct(private readonly string $packageRoot)
    {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $this->checks = 0;
        $this->failures = [];
        $this->observations = [];

        $this->conformanceParity();
        $this->endpointRegistryContract();
        $this->actionMappingContract();
        $this->failureInjectionContract();
        $this->eventProjectionContract();
        $this->serviceProviderContract();
        $this->determinismContract();

        return [
            'schema' => 'larena.telephony.asterisk_ami_core_test_receipt.v1',
            'schema_version' => '1.0.0',
            'status' => $this->failures === [] ? 'passed' : 'failed',
            'target' => 'Asterisk 22.x LTS AMI sanitized offline fixtures',
            'checks' => $this->checks,
            'observations' => $this->observations,
            'failures' => $this->failures,
            'nonclaims' => [
                'This receipt uses an injectable in-memory fixture transport and does not open a socket or connect to a PBX.',
                'Action acknowledgement is not proof of a call outcome, application runtime acceptance or production eligibility.',
                'No credentials, raw provider payloads, phone numbers, channels or provider identifiers are recorded in this receipt.',
            ],
        ];
    }

    private function conformanceParity(): void
    {
        $receipt = (new AdapterConformanceSuite($this->packageRoot))->run(new AsteriskAmiConformanceFixture());
        $this->check($receipt->verdict === 'conformant', 'Asterisk AMI fixture did not pass the public conformance suite.');
        $this->check(count($receipt->caseResults) === 14, 'Asterisk AMI fixture did not execute the unchanged 14-case suite.');
        $this->check(
            array_map(static fn ($case): string => $case->caseId, $receipt->caseResults) === AdapterConformanceSuite::REQUIRED_CASES,
            'Asterisk AMI fixture changed public conformance case identities.',
        );
        $this->observations['conformance'] = [
            'adapter_key' => $receipt->adapterKey,
            'verdict' => $receipt->verdict,
            'case_count' => count($receipt->caseResults),
            'receipt_fingerprint' => CanonicalJson::fingerprint($receipt->toArray()),
        ];
    }

    private function endpointRegistryContract(): void
    {
        $registry = new InMemoryEndpointRegistry();
        $endpoint = FixtureAmiContextResolver::endpointDescriptor();
        $registry->register($endpoint);
        $this->check($registry->find($endpoint->endpointId) === $endpoint, 'Endpoint registry did not resolve the registered descriptor.');
        $this->check(count($registry->all()) === 1, 'Endpoint registry returned an unexpected endpoint count.');

        $duplicateReason = null;
        try {
            $registry->register($endpoint);
        } catch (ContractViolation $exception) {
            $duplicateReason = $exception->reasonCode;
        }
        $this->check($duplicateReason === 'duplicate_endpoint_id', 'Endpoint registry accepted a duplicate endpoint identity.');

        $adapter = new AsteriskAmiAdapter(new FixtureAmiTransport(), new FixtureAmiContextResolver());
        $invalid = new \Larena\Telephony\ValueObjects\EndpointDescriptor(
            'endpoint.asterisk.invalid',
            'tenant.fixture',
            'larena.asterisk_ami',
            TelephonyContract::VERSION,
            ['ami_series' => '23.x', 'routing_profile_ref' => 'routing.invalid.fixture'],
            ['connection.primary' => 'secret://telephony/asterisk/invalid-fixture'],
            ['events.ingest', 'health.probe'],
            ['events.ingest', 'health.probe'],
        );
        $this->check(!$adapter->validateEndpoint($invalid)->valid, 'Asterisk endpoint accepted a non-frozen AMI series.');
    }

    private function actionMappingContract(): void
    {
        $expected = [
            'originate' => ['Action', 'ActionID', 'Async', 'Channel', 'Context', 'Exten', 'Priority', 'Timeout'],
            'hangup' => ['Action', 'ActionID', 'Channel'],
            'transfer' => ['Action', 'ActionID', 'Channel', 'Context', 'Exten', 'Priority'],
            'dtmf' => ['Action', 'ActionID', 'Channel', 'Digit', 'Duration'],
        ];
        $actionNames = [];
        $fingerprints = [];
        foreach ($expected as $commandType => $expectedFields) {
            $transport = new FixtureAmiTransport();
            $adapter = new AsteriskAmiAdapter($transport, new FixtureAmiContextResolver());
            $arguments = $commandType === 'dtmf' ? ['digit' => '5', 'duration_ms' => 160] : [];
            $command = $this->command($commandType, $arguments, 'mapping-'.$commandType);
            $result = $adapter->dispatch($command);
            $actions = $transport->recordedActions();
            $this->check($result->status === 'succeeded', "{$commandType} did not map to an acknowledged AMI action.");
            $this->check(count($actions) === 1, "{$commandType} did not dispatch exactly one AMI action.");
            if ($actions === []) {
                continue;
            }
            $action = $actions[0];
            $fields = array_keys($action->toArray());
            sort($expectedFields, SORT_STRING);
            $this->check($fields === $expectedFields, "{$commandType} AMI action fields differ from the frozen allowlist.");
            $this->check($action->actionId === AsteriskAmiAdapter::actionId($command), "{$commandType} ActionID is not deterministic.");
            $actionNames[$commandType] = $action->name;
            $fingerprints[$commandType] = $action->fingerprint();
        }
        $this->check($actionNames === [
            'originate' => 'Originate',
            'hangup' => 'Hangup',
            'transfer' => 'Redirect',
            'dtmf' => 'PlayDTMF',
        ], 'Domain commands differ from the frozen AMI action mapping.');

        $invalidDtmfTransport = new FixtureAmiTransport();
        $invalidDtmf = new AsteriskAmiAdapter($invalidDtmfTransport, new FixtureAmiContextResolver());
        $invalidDtmfResult = $invalidDtmf->dispatch($this->command('dtmf', ['digit' => '12'], 'invalid-dtmf'));
        $this->check($invalidDtmfResult->status === 'failed' && $invalidDtmfResult->reasonCode === 'invalid_dtmf_digit', 'Invalid DTMF was not rejected before transport.');
        $this->check($invalidDtmfTransport->recordedActions() === [], 'Invalid DTMF reached the transport.');

        $invalidChannelTransport = new FixtureAmiTransport();
        $invalidChannel = new AsteriskAmiAdapter($invalidChannelTransport, new FixtureAmiContextResolver(channel: 'PJSIP/.*'));
        $invalidChannelResult = $invalidChannel->dispatch($this->command('hangup', [], 'invalid-channel'));
        $this->check($invalidChannelResult->status === 'failed' && $invalidChannelResult->reasonCode === 'invalid_ami_channel', 'Pattern-like AMI channel was not rejected before transport.');
        $this->check($invalidChannelTransport->recordedActions() === [], 'Pattern-like AMI channel reached the transport.');

        $manifest = (new AsteriskAmiAdapter(new FixtureAmiTransport(), new FixtureAmiContextResolver()))->manifest();
        $this->check(!$manifest->supportsCommand('answer') && !$manifest->supportsCommand('hold') && !$manifest->supportsCommand('resume'), 'Asterisk adapter advertised answer, hold or resume.');
        $this->observations['actions'] = [
            'mapping' => $actionNames,
            'fingerprints' => $fingerprints,
            'unsupported' => ['answer', 'hold', 'resume'],
        ];
    }

    private function failureInjectionContract(): void
    {
        $observed = [];
        foreach ([
            'rejected' => 'failed',
            'timeout' => 'uncertain',
            'disconnected' => 'uncertain',
            'uncorrelated' => 'uncertain',
            'mismatched' => 'uncertain',
        ] as $outcome => $expectedStatus) {
            $command = $this->command('originate', [], 'failure-'.$outcome);
            $transport = new FixtureAmiTransport([AsteriskAmiAdapter::actionId($command) => $outcome]);
            $adapter = new AsteriskAmiAdapter($transport, new FixtureAmiContextResolver());
            $result = $adapter->dispatch($command);
            $this->check($result->status === $expectedStatus, "AMI {$outcome} outcome mapped to an unsafe command result.");
            $this->check(!$result->isSuccess(), "AMI {$outcome} outcome was mislabeled as success.");
            $observed[$outcome] = $result->status;
        }
        $throwingCommand = $this->command('originate', [], 'failure-exception');
        $throwingTransport = new class() implements \Larena\Telephony\Adapters\AsteriskAmi\Contracts\AmiTransport {
            public function send(
                \Larena\Telephony\ValueObjects\EndpointDescriptor $endpoint,
                \Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiAction $action,
            ): \Larena\Telephony\Adapters\AsteriskAmi\ValueObjects\AmiTransportResult {
                throw new \RuntimeException('sanitized fixture transport exception');
            }
        };
        $throwingResult = (new AsteriskAmiAdapter($throwingTransport, new FixtureAmiContextResolver()))->dispatch($throwingCommand);
        $this->check($throwingResult->status === 'uncertain' && $throwingResult->reasonCode === 'ami_transport_exception', 'Transport exception escaped the uncertain-result boundary.');
        $observed['exception'] = $throwingResult->status;
        $this->observations['failure_injection'] = $observed;
    }

    private function eventProjectionContract(): void
    {
        $runtime = $this->runtime();
        $events = [
            $this->event('lifecycle-created', 'Newchannel', '2026-01-01T10:01:00+00:00', 1),
            $this->event('lifecycle-dialing', 'DialBegin', '2026-01-01T10:02:00+00:00', 2),
            $this->event('lifecycle-ringing', 'Newstate', '2026-01-01T10:03:00+00:00', 3, ['ChannelStateDesc' => 'Ringing']),
            $this->event('lifecycle-answered', 'DialEnd', '2026-01-01T10:04:00+00:00', 4, ['DialStatus' => 'ANSWER']),
            $this->event('lifecycle-held', 'Hold', '2026-01-01T10:05:00+00:00', 5),
            $this->event('lifecycle-resumed', 'Unhold', '2026-01-01T10:06:00+00:00', 6),
            $this->event('lifecycle-ended', 'Hangup', '2026-01-01T10:07:00+00:00', 7),
        ];
        $statuses = [];
        $ingressResults = [];
        foreach ($events as $event) {
            $ingressResults[] = $runtime['processor']->ingest($event);
            $statuses[] = $ingressResults[array_key_last($ingressResults)]->status;
        }
        $duplicate = $runtime['processor']->ingest($events[1]);
        $terminalRegression = $runtime['processor']->ingest($this->event(
            'lifecycle-regression',
            'Newstate',
            '2026-01-01T10:08:00+00:00',
            8,
            ['ChannelStateDesc' => 'Ringing'],
        ));
        $session = $runtime['sessions']->all()[0] ?? null;
        $this->check($statuses === array_fill(0, 7, 'accepted'), 'Valid AMI lifecycle did not project deterministically.');
        $this->check($duplicate->status === 'duplicate', 'Repeated provider event mutated the call session.');
        $this->check($terminalRegression->status === 'ignored', 'Event after terminal state was not ignored.');
        $this->check($session?->state === 'ended' && $session->answeredAt !== null && $session->endedAt !== null, 'Projected call session timeline is incomplete.');
        $this->check(count($runtime['sessions']->all()) === 1, 'One provider call produced more than one logical call session.');
        $normalizedKeys = [];
        $firstEnvelope = $ingressResults[0]->envelope;
        if ($firstEnvelope === null) {
            $this->check(false, 'Accepted lifecycle event did not retain its canonical envelope.');
        } else {
            $normalizedKeys = array_keys($firstEnvelope->normalizedPayload);
            sort($normalizedKeys, SORT_STRING);
            $this->check($normalizedKeys === ['direction', 'local_party', 'remote_party', 'state'], 'Provider-specific AMI fields crossed into the canonical event payload.');
            $encodedEnvelope = CanonicalJson::encode($firstEnvelope->toArray());
            $this->check(!str_contains($encodedEnvelope, 'lifecycle-call') && !str_contains($encodedEnvelope, 'PJSIP/'), 'Canonical event envelope exposed a raw provider identifier or channel.');
        }

        $beforeUnknown = $session?->toArray();
        $unknown = $runtime['processor']->ingest($this->event('lifecycle-unknown', 'Mystery', '2026-01-01T10:09:00+00:00', 9));
        $malformed = $runtime['processor']->ingest(new ProviderEvent(
            'endpoint.asterisk.main',
            'larena.asterisk_ami',
            'lifecycle-malformed',
            null,
            new DateTimeImmutable('2026-01-01T10:10:00+00:00'),
            ['Sequence' => 10],
        ));
        $this->check($unknown->status === 'quarantined' && $malformed->status === 'quarantined', 'Unknown or malformed AMI event escaped quarantine.');
        $this->check(($runtime['sessions']->all()[0] ?? null)?->toArray() === $beforeUnknown, 'Quarantined AMI event mutated the call session.');

        $reordered = $this->runtime('reordered-call');
        $answerFirst = $reordered['processor']->ingest($this->event(
            'reordered-answer', 'DialEnd', '2026-01-01T10:04:00+00:00', 4, ['DialStatus' => 'ANSWER'], 'reordered-call',
        ));
        $createdLate = $reordered['processor']->ingest($this->event(
            'reordered-created', 'Newchannel', '2026-01-01T10:01:00+00:00', 1, [], 'reordered-call',
        ));
        $reorderedSession = $reordered['sessions']->all()[0] ?? null;
        $this->check($answerFirst->status === 'accepted' && $createdLate->status === 'accepted', 'Reordered answer/create events were not reconciled.');
        $this->check($reorderedSession?->state === 'answered' && $reorderedSession->startedAt->format(DATE_ATOM) === '2026-01-01T10:01:00+00:00', 'Late created event regressed state or failed to reconcile started_at.');

        $lateAnswer = $this->runtime('late-answer-call');
        $lateAnswer['processor']->ingest($this->event('late-created', 'Newchannel', '2026-01-01T10:01:00+00:00', 1, [], 'late-answer-call'));
        $lateAnswer['processor']->ingest($this->event('late-ended', 'Hangup', '2026-01-01T10:07:00+00:00', 7, [], 'late-answer-call'));
        $lateAnswerResult = $lateAnswer['processor']->ingest($this->event(
            'late-answered', 'DialEnd', '2026-01-01T10:04:00+00:00', 4, ['DialStatus' => 'ANSWER'], 'late-answer-call',
        ));
        $lateAnswerSession = $lateAnswer['sessions']->all()[0] ?? null;
        $this->check($lateAnswerResult->status === 'accepted', 'Late answered timestamp was not reconciled.');
        $this->check($lateAnswerSession?->state === 'ended' && $lateAnswerSession->answeredAt?->format(DATE_ATOM) === '2026-01-01T10:04:00+00:00', 'Late answered event changed terminal state or lost its safe timeline update.');

        $this->observations['event_projection'] = [
            'accepted_lifecycle_events' => count($events),
            'duplicate_status' => $duplicate->status,
            'terminal_regression_status' => $terminalRegression->status,
            'terminal_state' => $session?->state,
            'logical_session_count' => count($runtime['sessions']->all()),
            'reordered_state' => $reorderedSession?->state,
            'late_answer_state' => $lateAnswerSession?->state,
            'normalized_payload_keys' => $normalizedKeys,
        ];
    }

    private function serviceProviderContract(): void
    {
        $composer = json_decode((string) file_get_contents($this->packageRoot.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $providers = $composer['extra']['laravel']['providers'] ?? [];
        $this->check(
            $providers === ['Larena\\Telephony\\Providers\\TelephonyServiceProvider'],
            'Composer discovery does not expose exactly the Telephony service provider.',
        );

        $providerSource = (string) file_get_contents($this->packageRoot.'/src/Providers/TelephonyServiceProvider.php');
        foreach ([
            'AdapterRegistry::class',
            'EndpointRegistry::class',
            'CallSessionStore::class',
            'InMemoryEventInbox::class',
            'CallTransitionPolicy::class',
            'CallSessionProjector::class',
            'EventIngressProcessor::class',
        ] as $binding) {
            $this->check(str_contains($providerSource, $binding), "Service provider is missing the {$binding} core binding.");
        }
        $this->check(!str_contains($providerSource, 'Database') && !str_contains($providerSource, 'Network'), 'Service provider entered database or network composition.');
    }

    private function determinismContract(): void
    {
        $first = $this->deterministicScenario();
        $second = $this->deterministicScenario();
        $this->check(CanonicalJson::encode($first) === CanonicalJson::encode($second), 'Repeated fixture lifecycle was not byte-identical.');
        $this->observations['metamorphic'] = [
            'repeat_status' => 'byte_identical',
            'scenario_fingerprint' => CanonicalJson::fingerprint($first),
        ];
    }

    /** @return array<string, mixed> */
    private function deterministicScenario(): array
    {
        $runtime = $this->runtime('deterministic-call');
        $statuses = [];
        foreach ([
            $this->event('deterministic-created', 'Newchannel', '2026-01-01T11:01:00+00:00', 1, [], 'deterministic-call'),
            $this->event('deterministic-ringing', 'Newstate', '2026-01-01T11:02:00+00:00', 2, ['ChannelStateDesc' => 'Ringing'], 'deterministic-call'),
            $this->event('deterministic-ended', 'Hangup', '2026-01-01T11:03:00+00:00', 3, [], 'deterministic-call'),
        ] as $event) {
            $result = $runtime['processor']->ingest($event);
            $statuses[] = [$result->status, $result->reasonCode];
        }

        return [
            'statuses' => $statuses,
            'sessions' => array_map(static fn ($session): array => $session->toArray(), $runtime['sessions']->all()),
        ];
    }

    /** @return array{processor: EventIngressProcessor, sessions: InMemoryCallSessionStore} */
    private function runtime(string $providerCallRef = 'lifecycle-call'): array
    {
        $adapter = new AsteriskAmiAdapter(new FixtureAmiTransport(), new FixtureAmiContextResolver());
        $adapters = new AdapterRegistry();
        $adapters->register($adapter);
        $endpoints = new InMemoryEndpointRegistry();
        $endpoints->register(FixtureAmiContextResolver::endpointDescriptor());
        $sessions = new InMemoryCallSessionStore();
        $processor = new EventIngressProcessor(
            $adapters,
            $endpoints,
            new InMemoryEventInbox(),
            new CallSessionProjector($sessions, new CallTransitionPolicy()),
        );
        $this->activeProviderCallRef = $providerCallRef;

        return ['processor' => $processor, 'sessions' => $sessions];
    }

    private string $activeProviderCallRef = 'lifecycle-call';

    /** @param array<string, mixed> $extra */
    private function event(
        string $eventRef,
        string $eventType,
        string $occurredAt,
        int $sequence,
        array $extra = [],
        ?string $providerCallRef = null,
    ): ProviderEvent {
        $callRef = $providerCallRef ?? $this->activeProviderCallRef;

        return new ProviderEvent(
            'endpoint.asterisk.main',
            'larena.asterisk_ami',
            $eventRef,
            $callRef,
            new DateTimeImmutable($occurredAt),
            [
                'Event' => $eventType,
                'Linkedid' => $callRef,
                'Uniqueid' => $callRef.'-channel',
                'Sequence' => $sequence,
                ...$extra,
            ],
        );
    }

    /** @param array<string, mixed> $arguments */
    private function command(string $type, array $arguments, string $suffix): CallCommand
    {
        return new CallCommand(
            'command.asterisk.'.$suffix,
            $type,
            TelephonyContract::VERSION,
            'tenant.fixture',
            'actor.fixture',
            'endpoint.asterisk.main',
            $type === 'originate' ? null : 'call.asterisk.fixture',
            $arguments,
            'idempotency.asterisk.'.$suffix,
            'correlation.command.'.$suffix,
            new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
            'policy.fixture.commands',
            null,
        );
    }

    private function check(bool $condition, string $failure): void
    {
        ++$this->checks;
        if (!$condition) {
            $this->failures[] = $failure;
        }
    }
}
