<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\External;

use Composer\Semver\Semver;

final readonly class AdvisoryChecker
{
    private const ADVISORY_ENDPOINT = 'https://packagist.org/api/security-advisories/';
    private const CACHE_LIFETIME = 3600;

    public function __construct(
        private ExternalDataClient $externalDataClient,
    ) {}

    public function findAffectingAdvisories(array $packages): ?array
    {
        $installedVersions = [];
        foreach ($packages as $package) {
            if (!is_array($package) || ($package['devRequirement'] ?? false) === true) {
                continue;
            }
            $name = (string)($package['name'] ?? '');
            $version = (string)($package['version'] ?? '');
            if ($name !== '' && $this->isReleasedVersion($version)) {
                $installedVersions[$name] = $version;
            }
        }
        if ($installedVersions === []) {
            return [];
        }
        ksort($installedVersions);

        $response = $this->externalDataClient->postFormJson(
            self::ADVISORY_ENDPOINT,
            ['packages' => array_keys($installedVersions)],
            self::CACHE_LIFETIME,
        );
        if (!is_array($response['advisories'] ?? null)) {
            return null;
        }

        $affecting = [];
        foreach ($response['advisories'] as $packageName => $advisories) {
            $installedVersion = $installedVersions[$packageName] ?? null;
            if ($installedVersion === null || !is_array($advisories)) {
                continue;
            }
            foreach ($advisories as $advisory) {
                if (is_array($advisory) && $this->isAffected($installedVersion, (string)($advisory['affectedVersions'] ?? ''))) {
                    $affecting[] = [
                        'package' => (string)$packageName,
                        'version' => $installedVersion,
                        'title' => (string)($advisory['title'] ?? ''),
                        'identifier' => (string)(($advisory['cve'] ?? '') ?: ($advisory['advisoryId'] ?? '')),
                        'severity' => (string)($advisory['severity'] ?? ''),
                        'link' => (string)($advisory['link'] ?? ''),
                    ];
                }
            }
        }
        return $affecting;
    }

    private function isAffected(string $version, string $constraint): bool
    {
        if ($constraint === '') {
            return false;
        }
        try {
            return Semver::satisfies($version, $constraint);
        } catch (\Throwable) {
            return false;
        }
    }

    private function isReleasedVersion(string $version): bool
    {
        return $version !== '' && !str_starts_with($version, 'dev-') && !str_ends_with($version, '-dev');
    }
}
