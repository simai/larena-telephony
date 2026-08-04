<?php

declare(strict_types=1);

use Acme\LarenaTelephonyDemo\AcmeConformanceFixture;
use Larena\Telephony\Conformance\AdapterConformanceSuite;
use Larena\Telephony\Conformance\ComposerDependencyBoundary;
use Larena\Telephony\Support\CanonicalJson;
use Larena\Telephony\Testing\FakeAdapterConformanceFixture;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$mode = $argv[1] ?? '--run';
if (!in_array($mode, ['--run', '--write', '--check'], true)) {
    fwrite(STDERR, "Usage: php scripts/run-conformance.php [--run|--write|--check]\n");
    exit(2);
}

$suite = new AdapterConformanceSuite($root);
$fixtures = [
    'fake-adapter-receipt.json' => new FakeAdapterConformanceFixture(),
    'independent-adapter-receipt.json' => new AcmeConformanceFixture(),
];
$evidenceDir = $root.'/docs/project-management/evidence/g4-2-public-sdk-conformance';
$failures = [];
$summary = [];

foreach ($fixtures as $filename => $fixture) {
    $first = $suite->run($fixture);
    $second = $suite->run($fixture);
    $canonical = $first->toCanonicalJson();
    if ($canonical !== $second->toCanonicalJson()) {
        $failures[] = $first->adapterKey.': repeated suite execution was not byte-identical';
    }
    if ($first->verdict !== 'conformant' || count($first->caseResults) !== count(AdapterConformanceSuite::REQUIRED_CASES)) {
        $failures[] = $first->adapterKey.': conformance verdict is not a complete PASS';
    }

    $path = $evidenceDir.'/'.$filename;
    if ($mode === '--write') {
        if (!is_dir($evidenceDir) && !mkdir($evidenceDir, 0775, true) && !is_dir($evidenceDir)) {
            throw new RuntimeException("Unable to create evidence directory {$evidenceDir}");
        }
        file_put_contents($path, $canonical);
    } elseif ($mode === '--check') {
        if (!is_file($path) || file_get_contents($path) !== $canonical) {
            $failures[] = $first->adapterKey.': stored receipt is missing or stale';
        }
    }

    $summary[] = [
        'adapter_key' => $first->adapterKey,
        'composer_package' => $first->composerPackage,
        'verdict' => $first->verdict,
        'case_count' => count($first->caseResults),
        'receipt_fingerprint' => CanonicalJson::fingerprint($first->toArray()),
    ];
}

$fakeBoundary = ComposerDependencyBoundary::inspect($root.'/composer.json');
$independentPath = $root.'/tests/Fixtures/IndependentAdapter/composer.json';
$independentBoundary = ComposerDependencyBoundary::inspect($independentPath);
$independentManifest = json_decode((string) file_get_contents($independentPath), true, 512, JSON_THROW_ON_ERROR);
$reorderedManifest = $independentManifest;
if (isset($reorderedManifest['require']) && is_array($reorderedManifest['require'])) {
    $reorderedManifest['require'] = array_reverse($reorderedManifest['require'], true);
}
$temporaryPath = tempnam(sys_get_temp_dir(), 'larena-telephony-composer-');
if ($temporaryPath === false) {
    throw new RuntimeException('Unable to allocate temporary Composer fixture.');
}
try {
    file_put_contents($temporaryPath, json_encode($reorderedManifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    $reorderedBoundary = ComposerDependencyBoundary::inspect($temporaryPath);
} finally {
    unlink($temporaryPath);
}
if ($fakeBoundary['status'] !== 'passed' || $independentBoundary['status'] !== 'passed') {
    $failures[] = 'public Composer dependency boundary failed';
}
if ($independentBoundary['fingerprint'] !== $reorderedBoundary['fingerprint']) {
    $failures[] = 'Composer requirement ordering changed the dependency-boundary verdict';
}

$boundaryReport = [
    'schema' => 'larena.telephony.adapter_dependency_boundary.v1',
    'schema_version' => '1.0.0',
    'status' => $failures === [] ? 'passed' : 'failed',
    'base_package' => $fakeBoundary,
    'independent_package' => $independentBoundary,
    'metamorphic_requirement_order' => [
        'status' => $independentBoundary['fingerprint'] === $reorderedBoundary['fingerprint'] ? 'passed' : 'failed',
        'fingerprint' => $independentBoundary['fingerprint'],
    ],
    'nonclaims' => [
        'Dependency conformance proves the tested Composer boundary only.',
        'It does not certify vendor code, security, PBX connectivity or production eligibility.',
    ],
];
$boundaryPath = $evidenceDir.'/dependency-boundary.json';
$boundaryText = CanonicalJson::encode($boundaryReport);
if ($mode === '--write') {
    file_put_contents($boundaryPath, $boundaryText);
} elseif ($mode === '--check' && (!is_file($boundaryPath) || file_get_contents($boundaryPath) !== $boundaryText)) {
    $failures[] = 'stored dependency-boundary receipt is missing or stale';
}

$report = [
    'schema' => 'larena.telephony.conformance_run.v1',
    'status' => $failures === [] ? 'passed' : 'failed',
    'mode' => ltrim($mode, '-'),
    'suite_version' => \Larena\Telephony\Support\TelephonyContract::SUITE_VERSION,
    'required_case_count' => count(AdapterConformanceSuite::REQUIRED_CASES),
    'adapters' => $summary,
    'failures' => $failures,
];
fwrite(STDOUT, CanonicalJson::encode($report));
exit($failures === [] ? 0 : 1);
