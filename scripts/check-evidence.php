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
    if (($repository['revision'] ?? null) !== 'a087b3e1b23be4cec5c8bcde9408628851f32300'
        || ($repository['tree'] ?? null) !== '637d9a140d0373e7d99b6f31e5cb244ef8d4409a') {
        $failures[] = 'evidence_carrier_code_revision_mismatch';
    }
    foreach (($carrier['artifacts'] ?? []) as $artifact) {
        if (($artifact['id'] ?? null) === 'telephony_sdk_code_surface') {
            $sourceListing = shell_exec(
                'git -C '.escapeshellarg($root).' ls-tree -r '.escapeshellarg((string) ($repository['revision'] ?? '')).' src'
            );
            if (!is_string($sourceListing) || 'sha256:'.hash('sha256', $sourceListing) !== ($artifact['sha256'] ?? null)) {
                $failures[] = 'evidence_code_surface_hash_mismatch';
            }
            continue;
        }
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

$g43Status = 'not_required';
$g43Required = [];
$launchContextPath = $root.'/.larena/launch-context.json';
if (is_file($launchContextPath)) {
    $launchContext = json_decode((string) file_get_contents($launchContextPath), true, 512, JSON_THROW_ON_ERROR);
    $g43Active = in_array($launchContext['status'] ?? null, ['implemented_pending_acceptance', 'accepted'], true);
    if ($g43Active) {
        $g43Status = 'validating';
        $g43Evidence = $root.'/docs/project-management/evidence/g4-3-telephony-core-asterisk';
        $g43Required = [
            'implementation-summary.md',
            'tests.md',
            'fake-adapter-receipt.json',
            'independent-adapter-receipt.json',
            'asterisk-ami-adapter-receipt.json',
            'asterisk-ami-core-receipt.json',
            'dependency-boundary.json',
            'evidence-carrier.json',
            'graph-sync-proposal.json',
            'tester-verdict.json',
        ];
        foreach ($g43Required as $filename) {
            if (!is_file($g43Evidence.'/'.$filename)) {
                $failures[] = 'g4_3_missing_evidence:'.$filename;
            }
        }

        $requiredCaseIds = [
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
        foreach (['fake-adapter-receipt.json', 'independent-adapter-receipt.json', 'asterisk-ami-adapter-receipt.json'] as $filename) {
            $path = $g43Evidence.'/'.$filename;
            if (!is_file($path)) {
                continue;
            }
            $receipt = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $caseResults = $receipt['case_results'] ?? [];
            $caseIds = array_column($caseResults, 'case_id');
            if (($receipt['verdict'] ?? null) !== 'conformant'
                || $caseIds !== $requiredCaseIds
                || count(array_filter($caseResults, static fn (array $result): bool => ($result['status'] ?? null) !== 'passed')) !== 0) {
                $failures[] = 'g4_3_conformance_receipt_invalid:'.$filename;
            }
        }

        $coreReceiptPath = $g43Evidence.'/asterisk-ami-core-receipt.json';
        if (is_file($coreReceiptPath)) {
            $coreReceipt = json_decode((string) file_get_contents($coreReceiptPath), true, 512, JSON_THROW_ON_ERROR);
            if (($coreReceipt['status'] ?? null) !== 'passed' || ($coreReceipt['checks'] ?? 0) < 60 || ($coreReceipt['failures'] ?? null) !== []) {
                $failures[] = 'g4_3_asterisk_core_receipt_invalid';
            }
            $encodedReceipt = (string) file_get_contents($coreReceiptPath);
            foreach (['PJSIP/', 'top-secret', 'raw-provider-payload', 'provider-unique-id', 'secret://'] as $forbiddenEvidenceValue) {
                if (str_contains($encodedReceipt, $forbiddenEvidenceValue)) {
                    $failures[] = 'g4_3_core_receipt_contains_forbidden_value:'.$forbiddenEvidenceValue;
                }
            }
        }

        $g43BoundaryPath = $g43Evidence.'/dependency-boundary.json';
        if (is_file($g43BoundaryPath)) {
            $g43Boundary = json_decode((string) file_get_contents($g43BoundaryPath), true, 512, JSON_THROW_ON_ERROR);
            if (($g43Boundary['status'] ?? null) !== 'passed'
                || ($g43Boundary['base_package']['direct_larena_dependencies'] ?? null) !== []
                || ($g43Boundary['independent_package']['direct_larena_dependencies'] ?? null) !== ['larena/telephony']) {
                $failures[] = 'g4_3_dependency_boundary_invalid';
            }
        }

        $g43CarrierPath = $g43Evidence.'/evidence-carrier.json';
        if (is_file($g43CarrierPath)) {
            $g43Carrier = json_decode((string) file_get_contents($g43CarrierPath), true, 512, JSON_THROW_ON_ERROR);
            if (($g43Carrier['schema'] ?? null) !== 'larena.evidence_carrier.v1'
                || ($g43Carrier['outcome_id'] ?? null) !== 'larena.outcome.telephony.adapter_call_control.v1'
                || ($g43Carrier['acceptance_ids'] ?? null) !== ['larena.acceptance.telephony.adapter_call_control.vertical_3e822f24f29e51e2.v1']
                || ($g43Carrier['layer'] ?? null) !== 'tests_passed'
                || ($g43Carrier['status'] ?? null) !== 'proven') {
                $failures[] = 'g4_3_evidence_carrier_contract_mismatch';
            }
            $specification = $g43Carrier['bindings']['specification'] ?? [];
            if (($specification['revision'] ?? null) !== ($launchContext['specs_commit'] ?? null)
                || ($specification['tree'] ?? null) !== ($launchContext['specs_tree'] ?? null)
                || ($specification['state_hash'] ?? null) !== ($launchContext['technology_signature'] ?? null)) {
                $failures[] = 'g4_3_specification_binding_mismatch';
            }
            $repository = $g43Carrier['bindings']['repositories'][0] ?? [];
            $testedRevision = (string) ($repository['revision'] ?? '');
            $testedTree = (string) ($repository['tree'] ?? '');
            $resolvedTree = [];
            exec('git -C '.escapeshellarg($root).' show -s --format=%T '.escapeshellarg($testedRevision), $resolvedTree, $treeExit);
            if ($treeExit !== 0 || ($resolvedTree[0] ?? null) !== $testedTree) {
                $failures[] = 'g4_3_evidence_repository_revision_unresolvable';
            }
            exec('git -C '.escapeshellarg($root).' merge-base --is-ancestor '.escapeshellarg($testedRevision).' HEAD', $ancestorOutput, $ancestorExit);
            if ($ancestorExit !== 0) {
                $failures[] = 'g4_3_tested_revision_is_not_ancestor';
            }
            $codeSurface = shell_exec(
                'git -C '.escapeshellarg($root).' ls-tree -r '.escapeshellarg($testedRevision)
                .' -- README.md CHANGELOG.md module.yaml src scripts tests tools'
            );
            foreach (($g43Carrier['artifacts'] ?? []) as $artifact) {
                if (($artifact['id'] ?? null) === 'telephony_g4_3_code_surface') {
                    if (!is_string($codeSurface) || 'sha256:'.hash('sha256', $codeSurface) !== ($artifact['sha256'] ?? null)) {
                        $failures[] = 'g4_3_code_surface_hash_mismatch';
                    }
                    continue;
                }
                $artifactPath = $root.'/'.($artifact['path'] ?? '');
                if (!is_file($artifactPath) || 'sha256:'.hash_file('sha256', $artifactPath) !== ($artifact['sha256'] ?? null)) {
                    $failures[] = 'g4_3_artifact_hash_mismatch:'.($artifact['id'] ?? 'unknown');
                }
            }
            foreach (($g43Carrier['test_receipts'] ?? []) as $receipt) {
                if (($receipt['status'] ?? null) !== 'passed' || ($receipt['tested_revision'] ?? null) !== $testedRevision) {
                    $failures[] = 'g4_3_test_receipt_revision_mismatch:'.($receipt['id'] ?? 'unknown');
                }
            }
            if (($repository['lockfiles'][0]['sha256'] ?? null) !== 'sha256:'.hash_file('sha256', $root.'/composer.lock')) {
                $failures[] = 'g4_3_package_lock_binding_mismatch';
            }
        }

        $g43ProposalPath = $g43Evidence.'/graph-sync-proposal.json';
        if (is_file($g43ProposalPath)) {
            $proposal = json_decode((string) file_get_contents($g43ProposalPath), true, 512, JSON_THROW_ON_ERROR);
            $mustNotPromote = $proposal['must_not_promote'] ?? [];
            if (($proposal['status'] ?? null) !== 'proposal_only'
                || !in_array('workspace_presence', $mustNotPromote, true)
                || !in_array('root_application_runtime', $mustNotPromote, true)
                || !in_array('pbx_connected', $mustNotPromote, true)
                || !in_array('production_eligible', $mustNotPromote, true)) {
                $failures[] = 'g4_3_graph_sync_proposal_invalid';
            }
        }

        $g43VerdictPath = $g43Evidence.'/tester-verdict.json';
        if (is_file($g43VerdictPath)) {
            $verdict = json_decode((string) file_get_contents($g43VerdictPath), true, 512, JSON_THROW_ON_ERROR);
            if (($verdict['verdict'] ?? null) !== 'PASS_WITH_NOTES'
                || ($verdict['independence'] ?? null) !== 'self_technical_gatekeeper_not_independent_product_or_application_acceptance') {
                $failures[] = 'g4_3_technical_verdict_boundary_invalid';
            }
        }

        $g43Status = count(array_filter($failures, static fn (string $failure): bool => str_starts_with($failure, 'g4_3_'))) === 0
            ? 'passed'
            : 'failed';
    }
}

fwrite(STDOUT, json_encode([
    'schema' => 'larena.telephony.evidence_validation.v1',
    'status' => $failures === [] ? 'passed' : 'failed',
    'required_files' => $required,
    'g4_3_status' => $g43Status,
    'g4_3_required_files' => $g43Required,
    'failures' => $failures,
    'nonclaims' => ['Evidence validation cannot promote this package to PBX, application runtime or production readiness.'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
exit($failures === [] ? 0 : 1);
