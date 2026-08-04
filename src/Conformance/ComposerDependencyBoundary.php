<?php

declare(strict_types=1);

namespace Larena\Telephony\Conformance;

use JsonException;
use Larena\Telephony\Support\CanonicalJson;
use RuntimeException;

final class ComposerDependencyBoundary
{
    /** @return array<string, mixed> */
    public static function inspect(string $composerManifestPath): array
    {
        if (!is_file($composerManifestPath)) {
            throw new RuntimeException("Composer manifest does not exist: {$composerManifestPath}");
        }

        try {
            $manifest = json_decode((string) file_get_contents($composerManifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Composer manifest is not valid JSON.', previous: $exception);
        }
        if (!is_array($manifest)) {
            throw new RuntimeException('Composer manifest root must be an object.');
        }

        $requirements = [];
        foreach (['require', 'require-dev'] as $section) {
            foreach (($manifest[$section] ?? []) as $name => $constraint) {
                $requirements[(string) $name] = (string) $constraint;
            }
        }
        ksort($requirements, SORT_STRING);

        $larenaDependencies = array_values(array_filter(
            array_keys($requirements),
            static fn (string $name): bool => str_starts_with($name, 'larena/'),
        ));
        $violations = [];
        $isBasePackage = ($manifest['name'] ?? null) === 'larena/telephony';
        if (!$isBasePackage && !array_key_exists('larena/telephony', $requirements)) {
            $violations[] = 'missing_public_telephony_dependency';
        }
        foreach ($larenaDependencies as $dependency) {
            if ($dependency !== 'larena/telephony') {
                $violations[] = 'non_public_larena_dependency:'.$dependency;
            }
        }
        foreach (['larena/telephony-pro', 'larena/telephony-ai', 'larena/telephony-enterprise'] as $privateDependency) {
            if (array_key_exists($privateDependency, $requirements)) {
                $violations[] = 'private_telephony_dependency:'.$privateDependency;
            }
        }
        sort($violations, SORT_STRING);

        return [
            'status' => $violations === [] ? 'passed' : 'failed',
            'composer_package' => $manifest['name'] ?? null,
            'base_package_self_check' => $isBasePackage,
            'requirements' => $requirements,
            'direct_larena_dependencies' => $larenaDependencies,
            'private_access_required' => false,
            'commercial_entitlement_required' => false,
            'violations' => $violations,
            'fingerprint' => CanonicalJson::fingerprint([
                'composer_package' => $manifest['name'] ?? null,
                'requirements' => $requirements,
            ]),
        ];
    }

    private function __construct()
    {
    }
}
