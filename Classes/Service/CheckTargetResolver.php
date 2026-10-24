<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Service;

use Tenryuubito\WizMonitor\Domain\Model\Project;
use Tenryuubito\WizMonitor\Domain\Model\Snapshot;

final readonly class CheckTargetResolver
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function resolveUrls(Project $project, ?Snapshot $snapshot): array
    {
        $pageUrls = array_map(
            static fn(array $item): string => $item['url'],
            $this->findPageItems($project, $snapshot),
        );
        return array_values(array_unique([...$project->checkUrls, ...$pageUrls]));
    }

    public function findUnresolvedPageUids(Project $project, ?Snapshot $snapshot): array
    {
        $pageData = $snapshot?->getCollectorData('pages');
        if ($project->checkPageUids === [] || $pageData === null) {
            return [];
        }
        $resolvedPageUids = array_map(
            static fn(array $item): int => $item['pageUid'],
            $this->findPageItems($project, $snapshot),
        );
        return array_values(array_diff($project->checkPageUids, $resolvedPageUids));
    }

    private function findPageItems(Project $project, ?Snapshot $snapshot): array
    {
        $items = $snapshot?->getCollectorData('pages')['items'] ?? [];
        if ($project->checkPageUids === [] || !is_array($items)) {
            return [];
        }
        return array_values(array_filter(
            $items,
            fn(mixed $item): bool => is_array($item)
                && is_int($item['pageUid'] ?? null)
                && in_array($item['pageUid'], $project->checkPageUids, true)
                && is_string($item['url'] ?? null)
                && $this->isAllowedUrl($item['url']),
        ));
    }

    private function isAllowedUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), self::ALLOWED_SCHEMES, true);
    }
}
