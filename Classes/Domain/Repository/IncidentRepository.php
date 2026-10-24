<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Domain\Repository;

use Tenryuubito\WizMonitor\Enum\HealthCategory;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class IncidentRepository
{
    public const TABLE_NAME = 'tx_wizmonitor_incident';
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function findOpen(int $projectUid, HealthCategory $category): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('project', $queryBuilder->createNamedParameter($projectUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('category', $queryBuilder->createNamedParameter($category->value)),
                $queryBuilder->expr()->eq('resolved_at', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('opened_at', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $row;
    }

    public function open(int $projectUid, HealthCategory $category, HealthStatus $status, array $findings, int $timestamp): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_NAME)->insert(self::TABLE_NAME, [
            'project' => $projectUid,
            'category' => $category->value,
            'status' => $status->value,
            'findings' => json_encode($findings, self::JSON_FLAGS),
            'opened_at' => $timestamp,
            'updated_at' => $timestamp,
            'resolved_at' => 0,
        ]);
    }

    public function update(int $uid, HealthStatus $status, array $findings, int $timestamp): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_NAME)->update(self::TABLE_NAME, [
            'status' => $status->value,
            'findings' => json_encode($findings, self::JSON_FLAGS),
            'updated_at' => $timestamp,
        ], ['uid' => $uid]);
    }

    public function resolve(int $uid, int $timestamp): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_NAME)->update(self::TABLE_NAME, [
            'updated_at' => $timestamp,
            'resolved_at' => $timestamp,
        ], ['uid' => $uid]);
    }

    public function findLatest(int $projectUid, int $limit): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('project', $queryBuilder->createNamedParameter($projectUid, Connection::PARAM_INT)))
            ->orderBy('opened_at', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function removeResolvedBefore(int $timestamp): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->gt('resolved_at', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->lt('resolved_at', $queryBuilder->createNamedParameter($timestamp, Connection::PARAM_INT)),
            )
            ->executeStatement();
    }
}
