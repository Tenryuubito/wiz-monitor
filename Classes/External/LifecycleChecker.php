<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\External;

final readonly class LifecycleChecker
{
    private const TYPO3_API = 'https://get.typo3.org/api/v1/major/';
    private const PHP_CYCLES_API = 'https://endoflife.date/api/php.json';
    private const CACHE_LIFETIME = 21600;

    public function __construct(
        private ExternalDataClient $externalDataClient,
    ) {}

    public function getTypo3MajorLifecycle(int $majorVersion): ?array
    {
        $data = $this->externalDataClient->getJson(self::TYPO3_API . $majorVersion, self::CACHE_LIFETIME);
        if ($data === null) {
            return null;
        }
        return [
            'maintainedUntil' => $this->toTimestamp($data['maintained_until'] ?? null),
            'eltsUntil' => $this->toTimestamp($data['elts_until'] ?? null),
        ];
    }

    public function getTypo3LatestVersion(int $majorVersion): ?string
    {
        return $this->fetchReleaseVersion(self::TYPO3_API . $majorVersion . '/release/latest');
    }

    public function getTypo3LatestSecurityVersion(int $majorVersion): ?string
    {
        return $this->fetchReleaseVersion(self::TYPO3_API . $majorVersion . '/release/latest/security');
    }

    public function getPhpCycle(string $phpVersion): ?array
    {
        $cycle = implode('.', array_slice(explode('.', $phpVersion), 0, 2));
        $cycles = $this->externalDataClient->getJson(self::PHP_CYCLES_API, self::CACHE_LIFETIME);
        foreach ($cycles ?? [] as $entry) {
            if (is_array($entry) && (string)($entry['cycle'] ?? '') === $cycle) {
                return [
                    'cycle' => $cycle,
                    'activeSupportUntil' => $this->toTimestamp($entry['support'] ?? null),
                    'securitySupportUntil' => $this->toTimestamp($entry['eol'] ?? null),
                ];
            }
        }
        return null;
    }

    private function fetchReleaseVersion(string $url): ?string
    {
        $version = $this->externalDataClient->getJson($url, self::CACHE_LIFETIME)['version'] ?? null;
        return is_string($version) && $version !== '' ? $version : null;
    }

    private function toTimestamp(mixed $value): int
    {
        if (!is_string($value) || $value === '') {
            return 0;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : $timestamp;
    }
}
