<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$required = [
    '.larena/launch-context.json',
    '.larena/spec-ref.json',
    'LICENSE',
    'README.md',
    'composer.json',
    'composer.lock',
    'module.yaml',
    'src/Contracts/TelephonyAdapter.php',
    'src/Conformance/AdapterConformanceSuite.php',
    'src/Registry/AdapterRegistry.php',
    'src/Testing/FakeTelephonyAdapter.php',
    'tests/Fixtures/IndependentAdapter/composer.json',
];
foreach ($required as $relative) {
    if (!is_file($root.'/'.$relative)) {
        $failures[] = 'missing_file:'.$relative;
    }
}

$composer = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
if (($composer['name'] ?? null) !== 'larena/telephony') {
    $failures[] = 'composer_identity_mismatch';
}
if (($composer['license'] ?? null) !== 'Apache-2.0') {
    $failures[] = 'license_mismatch';
}
$requirements = $composer['require'] ?? [];
foreach (array_keys($requirements) as $dependency) {
    if (str_starts_with((string) $dependency, 'larena/')) {
        $failures[] = 'unexpected_larena_runtime_dependency:'.$dependency;
    }
}

fwrite(STDOUT, json_encode([
    'schema' => 'larena.telephony.package_validation.v1',
    'status' => $failures === [] ? 'passed' : 'failed',
    'package' => $composer['name'] ?? null,
    'license' => $composer['license'] ?? null,
    'failures' => $failures,
    'nonclaims' => ['Package structure validation does not prove runtime or production readiness.'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
exit($failures === [] ? 0 : 1);
