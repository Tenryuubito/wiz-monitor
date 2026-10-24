<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class ErrorRepository
{
    public const TABLE_NAME = 'tx_wizmonitor_error';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function synchronize(int $projectUid, array $items): array
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);
        $newItems = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['fingerprint'] ?? null) || $item['fingerprint'] === '') {
                continue;
            }
            $fields = [
                'level' => mb_substr((string)($item['level'] ?? ''), 0, 16),
                'channel' => mb_substr((string)($item['channel'] ?? ''), 0, 255),
                'message' => (string)($item['message'] ?? ''),
                'exception_class' => mb_substr((string)($item['exceptionClass'] ?? ''), 0, 255),
                'exception_code' => mb_substr((string)($item['exceptionCode'] ?? ''), 0, 64),
                'file' => mb_substr((string)($item['file'] ?? ''), 0, 1024),
                'line' => max(0, (int)($item['line'] ?? 0)),
                'request_url' => mb_substr((string)($item['requestUrl'] ?? ''), 0, 1024),
                'occurrences' => max(0, (int)($item['occurrences'] ?? 0)),
                'first_seen' => max(0, (int)($item['firstSeen'] ?? 0)),
                'last_seen' => max(0, (int)($item['lastSeen'] ?? 0)),
            ];
            $identifier = ['project' => $projectUid, 'fingerprint' => mb_substr($item['fingerprint'], 0, 64)];
            if ($this->exists($identifier)) {
                $connection->update(self::TABLE_NAME, $fields, $identifier);
                continue;
            }
            $connection->insert(self::TABLE_NAME, $identifier + $fields);
            $newItems[] = $identifier + $fields;
        }
        return $newItems;
    }

    public function findSeenSince(int $projectUid, int $timestamp): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('project', $queryBuilder->createNamedParameter($projectUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->gte('last_seen', $queryBuilder->createNamedParameter($timestamp, Connection::PARAM_INT)),
            )
            ->orderBy('last_seen', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function findLatest(int $projectUid, int $limit): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('project', $queryBuilder->createNamedParameter($projectUid, Connection::PARAM_INT)))
            ->orderBy('last_seen', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function removeLastSeenBefore(int $timestamp): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where($queryBuilder->expr()->lt('last_seen', $queryBuilder->createNamedParameter($timestamp, Connection::PARAM_INT)))
            ->executeStatement();
    }

    private function exists(array $identifier): bool
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE_NAME)->count('uid', self::TABLE_NAME, $identifier) > 0;
    }
}
