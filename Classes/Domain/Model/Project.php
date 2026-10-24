<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Domain\Model;

use Tenryuubito\WizMonitorClient\Status\StatusRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class Project
{
    public function __construct(
        public int $uid,
        public string $title,
        public string $baseUrl,
        public array $checkUrls,
        public array $checkPageUids,
        public string $expectedKeyword,
        public string $encryptedSecret,
        public bool $disabled,
    ) {}

    public static function fromRow(array $row): self
    {
        $baseUrl = rtrim(trim((string)$row['base_url']), '/');
        $checkUrls = array_values(array_unique(array_filter(
            array_map(
                static fn(string $path): string => $baseUrl . '/' . ltrim($path, '/'),
                array_filter(
                    array_map('trim', preg_split('/\R/', (string)($row['check_urls'] ?? '')) ?: []),
                    self::isRelativePath(...),
                ),
            ),
            static fn(string $url): bool => filter_var($url, FILTER_VALIDATE_URL) !== false,
        )));

        return new self(
            uid: (int)$row['uid'],
            title: (string)$row['title'],
            baseUrl: $baseUrl,
            checkUrls: $checkUrls !== [] ? $checkUrls : [$baseUrl . '/'],
            checkPageUids: StatusRequest::normalizePageUids(
                GeneralUtility::intExplode(',', (string)($row['check_pages'] ?? ''), true),
            ),
            expectedKeyword: trim((string)($row['expected_keyword'] ?? '')),
            encryptedSecret: (string)($row['secret_encrypted'] ?? ''),
            disabled: (bool)($row['hidden'] ?? false),
        );
    }

    private static function isRelativePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '//')) {
            return false;
        }
        $parts = parse_url($path);
        return $parts !== false && !isset($parts['scheme']) && !isset($parts['host']);
    }

    public function isSecretConfigured(): bool
    {
        return $this->encryptedSecret !== '';
    }
}
