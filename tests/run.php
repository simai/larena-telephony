<?php

declare(strict_types=1);

use Acme\LarenaTelephonyDemo\AcmeConformanceFixture;
use Larena\Telephony\Conformance\AdapterConformanceSuite;
use Larena\Telephony\Conformance\ComposerDependencyBoundary;
use Larena\Telephony\Exceptions\ContractViolation;
use Larena\Telephony\Support\CanonicalJson;
use Larena\Telephony\Support\ContractVersion;
use Larena\Telephony\Testing\AsteriskAmi\AsteriskAmiConformanceFixture;
use Larena\Telephony\Testing\AsteriskAmi\AsteriskAmiCoreTestSuite;
use Larena\Telephony\Testing\FakeAdapterConformanceFixture;
use Larena\Telephony\ValueObjects\CallParty;
use Larena\Telephony\ValueObjects\CallSession;
use Larena\Telephony\ValueObjects\EndpointDescriptor;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$checks = 0;
/** @var list<string> $failures */
$failures = [];

$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    ++$checks;
    if (!$condition) {
        $failures[] = $message;
    }
};

$suite = new AdapterConformanceSuite($root);
foreach ([new FakeAdapterConformanceFixture(), new AcmeConformanceFixture(), new AsteriskAmiConformanceFixture()] as $fixture) {
    $first = $suite->run($fixture);
    $second = $suite->run($fixture);
    $check($first->verdict === 'conformant', $first->adapterKey.' did not pass conformance');
    $check(count($first->caseResults) === 14, $first->adapterKey.' did not execute 14 cases');
    $check(
        array_map(static fn ($case): string => $case->caseId, $first->caseResults) === AdapterConformanceSuite::REQUIRED_CASES,
        $first->adapterKey.' case identities differ from the public suite',
    );
    $check($first->toCanonicalJson() === $second->toCanonicalJson(), $first->adapterKey.' receipt is non-deterministic');
    $check(
        array_filter($first->caseResults, static fn ($case): bool => $case->status !== 'passed') === [],
        $first->adapterKey.' contains failed conformance cases',
    );
}

$asteriskCore = (new AsteriskAmiCoreTestSuite($root))->run();
$check(($asteriskCore['status'] ?? null) === 'passed', 'Asterisk AMI core fixture suite failed');
$check(($asteriskCore['checks'] ?? 0) >= 50, 'Asterisk AMI core fixture suite did not execute the frozen coverage');

$versionRejected = false;
try {
    ContractVersion::parse('1');
} catch (ContractViolation $exception) {
    $versionRejected = $exception->reasonCode === 'invalid_contract_version';
}
$check($versionRejected, 'Malformed contract version was accepted');

$secretRejected = false;
try {
    new EndpointDescriptor(
        'endpoint.negative.secret',
        'tenant.fixture',
        'larena.fake',
        '1.0.0',
        ['api_key' => 'must-not-pass'],
        ['connection.primary' => 'secret://telephony/test'],
        ['events.ingest'],
        ['events.ingest'],
    );
} catch (ContractViolation $exception) {
    $secretRejected = $exception->reasonCode === 'raw_secret_forbidden';
}
$check($secretRejected, 'Raw endpoint credential was accepted');

$call = new CallSession(
    'call.fixture-001',
    'tenant.fixture',
    'endpoint.fixture',
    'larena.fake',
    'sha256:'.str_repeat('a', 64),
    'inbound',
    'answered',
    new CallParty('extension.support', 'extension'),
    new CallParty('external.sha256-a1', 'external'),
    new DateTimeImmutable('2026-08-03T09:00:00+00:00'),
    new DateTimeImmutable('2026-08-03T09:00:03+00:00'),
    null,
    'correlation.fixture-call',
    ['crm.contact-17'],
    ['artifact://telephony/recording-17'],
    2,
);
$callArray = $call->toArray();
$check($callArray['call_id'] === 'call.fixture-001' && $callArray['version'] === 2, 'Normalized CallSession contract changed');
$check(!array_key_exists('provider_call_ref', $callArray), 'CallSession exposed a raw provider call reference');

$rawProviderReferenceRejected = false;
try {
    new CallSession(
        'call.fixture-raw-provider',
        'tenant.fixture',
        'endpoint.fixture',
        'larena.fake',
        'provider-call-123',
        'inbound',
        'new',
        new CallParty('extension.support', 'extension'),
        new CallParty('external.sha256-a1', 'external'),
        new DateTimeImmutable('2026-08-03T09:00:00+00:00'),
        null,
        null,
        'correlation.fixture-raw-provider',
        [],
        [],
        0,
    );
} catch (ContractViolation $exception) {
    $rawProviderReferenceRejected = $exception->reasonCode === 'invalid_provider_call_ref_hash';
}
$check($rawProviderReferenceRejected, 'CallSession accepted a raw provider call reference');

$invalidTimelineRejected = false;
try {
    new CallSession(
        'call.fixture-invalid-timeline',
        'tenant.fixture',
        'endpoint.fixture',
        'larena.fake',
        'sha256:'.str_repeat('b', 64),
        'outbound',
        'answered',
        new CallParty('extension.sales', 'extension'),
        new CallParty('external.sha256-b2', 'external'),
        new DateTimeImmutable('2026-08-03T09:00:05+00:00'),
        new DateTimeImmutable('2026-08-03T09:00:01+00:00'),
        null,
        'correlation.fixture-invalid-timeline',
        [],
        [],
        1,
    );
} catch (ContractViolation $exception) {
    $invalidTimelineRejected = $exception->reasonCode === 'invalid_call_timestamp_order';
}
$check($invalidTimelineRejected, 'CallSession accepted reversed timestamps');

$privateManifest = [
    'name' => 'vendor/unsafe-adapter',
    'require' => [
        'php' => '^8.3',
        'larena/telephony' => '^1.0',
        'larena/telephony-pro' => '^1.0',
    ],
];
$temporaryPath = tempnam(sys_get_temp_dir(), 'larena-telephony-negative-');
if ($temporaryPath === false) {
    throw new RuntimeException('Unable to allocate negative fixture.');
}
try {
    file_put_contents($temporaryPath, json_encode($privateManifest, JSON_THROW_ON_ERROR));
    $boundary = ComposerDependencyBoundary::inspect($temporaryPath);
    $check($boundary['status'] === 'failed', 'Private Larena adapter dependency was accepted');
    $check(
        in_array('private_telephony_dependency:larena/telephony-pro', $boundary['violations'], true),
        'Private dependency rejection reason was not explicit',
    );
} finally {
    unlink($temporaryPath);
}

$report = [
    'schema' => 'larena.telephony.package_test_run.v1',
    'status' => $failures === [] ? 'passed' : 'failed',
    'checks' => $checks,
    'failures' => $failures,
    'nonclaims' => [
        'These deterministic tests do not access a PBX, network, database, media or production system.',
    ],
];
fwrite(STDOUT, CanonicalJson::encode($report));
exit($failures === [] ? 0 : 1);
