<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Domain\Repository;

use Tenryuubito\WizMonitor\Domain\Model\ProbeResult;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class CheckRepository
{
    public const TABLE_NAME = 'tx_wizmonitor_check';
    private const RECENT_ROW_LIMIT = 200;

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function add(int $projectUid, ProbeResult $result, int $checkedAt): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_NAME)->insert(self::TABLE_NAME, [
            'project' => $projectUid,
            'checked_at' => $checkedAt,
            'url' => mb_substr($result->url, 0, 1024),
            'reachable' => (int)$result->reachable,
            'http_status' => $result->httpStatus,
            'response_time' => $result->responseTimeMilliseconds,
            'keyword_found' => $result->keywordFound === null ? -1 : (int)$result->keywordFound,
            'tls_expires_at' => $result->tlsExpiresAt,
            'failure_reason' => mb_substr($result->failureReason, 0, 1024),
        ]);
    }

    public function findRecentGroupedByUrl(int $projectUid, array $urls, int $checksPerUrl): array
    {
        if ($urls === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('project', $queryBuilder->createNamedParameter($projectUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('url', $queryBuilder->createNamedParameter(array_values($urls), Connection::PARAM_STR_ARRAY)),
            )
            ->orderBy('checked_at', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setMaxResults(self::RECENT_ROW_LIMIT)
            ->executeQuery()
            ->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $url = (string)$row['url'];
            if (count($grouped[$url] ?? []) < $checksPerUrl) {
                $grouped[$url][] = $row;
            }
        }
        return $grouped;
    }

    public function removeCheckedBefore(int $timestamp): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where($queryBuilder->expr()->lt('checked_at', $queryBuilder->createNamedParameter($timestamp, Connection::PARAM_INT)))
            ->executeStatement();
    }
}
