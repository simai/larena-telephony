<?php

declare(strict_types=1);

use Larena\Telephony\Support\CanonicalJson;
use Larena\Telephony\Testing\AsteriskAmi\AsteriskAmiCoreTestSuite;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$mode = $argv[1] ?? '--run';
if (!in_array($mode, ['--run', '--write', '--check'], true)) {
    fwrite(STDERR, "Usage: php scripts/run-asterisk-ami-tests.php [--run|--write|--check]\n");
    exit(2);
}

$suite = new AsteriskAmiCoreTestSuite($root);
$first = $suite->run();
$second = $suite->run();
$canonical = CanonicalJson::encode($first);
$evidenceDir = $root.'/docs/project-management/evidence/g4-3-telephony-core-asterisk';
$evidencePath = $evidenceDir.'/asterisk-ami-core-receipt.json';
$failures = $first['failures'] ?? [];
if ($canonical !== CanonicalJson::encode($second)) {
    $failures[] = 'repeated_core_fixture_run_not_byte_identical';
}
if ($mode === '--write') {
    if (!is_dir($evidenceDir) && !mkdir($evidenceDir, 0775, true) && !is_dir($evidenceDir)) {
        throw new RuntimeException("Unable to create evidence directory {$evidenceDir}");
    }
    file_put_contents($evidencePath, $canonical);
} elseif ($mode === '--check' && (!is_file($evidencePath) || file_get_contents($evidencePath) !== $canonical)) {
    $failures[] = 'stored_asterisk_ami_core_receipt_missing_or_stale';
}

$report = [
    'schema' => 'larena.telephony.asterisk_ami_core_test_run.v1',
    'status' => $failures === [] ? 'passed' : 'failed',
    'mode' => ltrim($mode, '-'),
    'checks' => $first['checks'] ?? 0,
    'receipt_fingerprint' => CanonicalJson::fingerprint($first),
    'failures' => $failures,
    'nonclaims' => $first['nonclaims'] ?? [],
];
fwrite(STDOUT, CanonicalJson::encode($report));
exit($failures === [] ? 0 : 1);
