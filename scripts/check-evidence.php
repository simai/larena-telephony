<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$evidence = $root.'/docs/project-management/evidence/g4-2-public-sdk-conformance';
$required = [
    'implementation-summary.md',
    'tests.md',
    'fake-adapter-receipt.json',
    'independent-adapter-receipt.json',
    'dependency-boundary.json',
    'evidence-carrier.json',
    'graph-sync-proposal.json',
    'tester-verdict.json',
];
$failures = [];
foreach ($required as $filename) {
    if (!is_file($evidence.'/'.$filename)) {
        $failures[] = 'missing_evidence:'.$filename;
    }
}

foreach (['fake-adapter-receipt.json', 'independent-adapter-receipt.json'] as $filename) {
    $path = $evidence.'/'.$filename;
    if (!is_file($path)) {
        continue;
    }
    $receipt = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $caseResults = $receipt['case_results'] ?? [];
    if (($receipt['verdict'] ?? null) !== 'conformant') {
        $failures[] = $filename.':verdict_not_conformant';
    }
    if (count($caseResults) !== 14 || count(array_unique(array_column($caseResults, 'case_id'))) !== 14) {
        $failures[] = $filename.':case_identity_mismatch';
    }
    foreach ($caseResults as $result) {
        if (($result['status'] ?? null) !== 'passed') {
            $failures[] = $filename.':failed_case:'.($result['case_id'] ?? 'unknown');
        }
    }
    if (($receipt['nonclaims'] ?? []) === []) {
        $failures[] = $filename.':missing_nonclaims';
    }
}

$boundaryPath = $evidence.'/dependency-boundary.json';
if (is_file($boundaryPath)) {
    $boundary = json_decode((string) file_get_contents($boundaryPath), true, 512, JSON_THROW_ON_ERROR);
    if (($boundary['status'] ?? null) !== 'passed') {
        $failures[] = 'dependency_boundary_not_passed';
    }
    if (($boundary['independent_package']['direct_larena_dependencies'] ?? null) !== ['larena/telephony']) {
        $failures[] = 'independent_adapter_larena_dependency_boundary_changed';
    }
}

$proposalPath = $evidence.'/graph-sync-proposal.json';
if (is_file($proposalPath)) {
    $proposal = json_decode((string) file_get_contents($proposalPath), true, 512, JSON_THROW_ON_ERROR);
    if (($proposal['status'] ?? null) !== 'proposal_only') {
        $failures[] = 'graph_sync_must_remain_proposal_only';
    }
}

$carrierPath = $evidence.'/evidence-carrier.json';
if (is_file($carrierPath)) {
    $carrier = json_decode((string) file_get_contents($carrierPath), true, 512, JSON_THROW_ON_ERROR);
    if (($carrier['schema'] ?? null) !== 'larena.evidence_carrier.v1'
        || ($carrier['layer'] ?? null) !== 'tests_passed'
        || ($carrier['status'] ?? null) !== 'proven') {
        $failures[] = 'evidence_carrier_contract_mismatch';
    }
    $repository = $carrier['bindings']['repositories'][0] ?? [];
    if (($repository['revision'] ?? null) !== 'c6c5d39955935304ba03c176052ae4b074cd481f'
        || ($repository['tree'] ?? null) !== '76c187bcf195b2f01084a6dc9ac53d334ce13c89') {
        $failures[] = 'evidence_carrier_code_revision_mismatch';
    }
    foreach (($carrier['artifacts'] ?? []) as $artifact) {
        $path = $root.'/'.($artifact['path'] ?? '');
        if (str_contains((string) ($artifact['path'] ?? ''), '*')) {
            continue;
        }
        if (!is_file($path) || 'sha256:'.hash_file('sha256', $path) !== ($artifact['sha256'] ?? null)) {
            $failures[] = 'evidence_artifact_hash_mismatch:'.($artifact['id'] ?? 'unknown');
        }
    }
    $review = $carrier['review'] ?? [];
    $reviewPath = $root.'/'.($review['artifact_ref'] ?? '');
    if (!is_file($reviewPath) || 'sha256:'.hash_file('sha256', $reviewPath) !== ($review['sha256'] ?? null)) {
        $failures[] = 'evidence_review_hash_mismatch';
    }
    $testedRevision = (string) ($repository['revision'] ?? '');
    $testedTree = (string) ($repository['tree'] ?? '');
    $actualTree = [];
    $treeExit = 0;
    exec('git -C '.escapeshellarg($root).' show -s --format=%T '.escapeshellarg($testedRevision), $actualTree, $treeExit);
    if ($treeExit !== 0 || ($actualTree[0] ?? null) !== $testedTree) {
        $failures[] = 'evidence_repository_revision_unresolvable';
    }
}

$verdictPath = $evidence.'/tester-verdict.json';
if (is_file($verdictPath)) {
    $verdict = json_decode((string) file_get_contents($verdictPath), true, 512, JSON_THROW_ON_ERROR);
    if (($verdict['verdict'] ?? null) !== 'PASS_WITH_NOTES') {
        $failures[] = 'technical_verdict_not_pass_with_notes';
    }
    if (($verdict['independence'] ?? null) !== 'self_technical_gatekeeper_not_independent_product_or_runtime_acceptance') {
        $failures[] = 'technical_verdict_independence_boundary_missing';
    }
}

fwrite(STDOUT, json_encode([
    'schema' => 'larena.telephony.evidence_validation.v1',
    'status' => $failures === [] ? 'passed' : 'failed',
    'required_files' => $required,
    'failures' => $failures,
    'nonclaims' => ['Evidence validation cannot promote this package to PBX, application runtime or production readiness.'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
exit($failures === [] ? 0 : 1);
