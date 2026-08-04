<?php

declare(strict_types=1);

namespace Larena\Telephony\ValueObjects;

use Larena\Telephony\Support\CanonicalJson;

final readonly class CompatibilityReceipt
{
    /**
     * @param list<ConformanceCaseResult> $caseResults
     * @param list<string> $nonclaims
     */
    public function __construct(
        public string $adapterKey,
        public string $composerPackage,
        public string $packageVersion,
        public string $contractVersion,
        public string $suiteVersion,
        public string $fixtureFingerprint,
        public string $environmentFingerprint,
        public array $caseResults,
        public string $verdict,
        public array $nonclaims,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $results = array_map(
            static fn (ConformanceCaseResult $result): array => $result->toArray(),
            $this->caseResults,
        );
        usort($results, static fn (array $left, array $right): int => $left['case_id'] <=> $right['case_id']);

        return [
            'schema' => 'larena.telephony.compatibility_receipt.v1',
            'schema_version' => '1.0.0',
            'adapter_key' => $this->adapterKey,
            'composer_package' => $this->composerPackage,
            'package_version' => $this->packageVersion,
            'contract_version' => $this->contractVersion,
            'suite_version' => $this->suiteVersion,
            'fixture_fingerprint' => $this->fixtureFingerprint,
            'environment_fingerprint' => $this->environmentFingerprint,
            'case_results' => $results,
            'verdict' => $this->verdict,
            'nonclaims' => $this->nonclaims,
        ];
    }

    public function toCanonicalJson(): string
    {
        return CanonicalJson::encode($this->toArray());
    }
}
