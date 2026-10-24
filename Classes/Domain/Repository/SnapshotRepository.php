<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Domain\Repository;

use Tenryuubito\WizMonitor\Domain\Model\Snapshot;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class SnapshotRepository
{
    public const TABLE_NAME = 'tx_wizmonitor_snapshot';
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function findByProject(int $projectUid): ?Snapshot
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('project', $queryBuilder->createNamedParameter($projectUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : Snapshot::fromRow($row);
    }

    public function findAllIndexedByProject(): array
    {
        $rows = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME)
            ->select('*')
            ->from(self::TABLE_NAME)
            ->executeQuery()
            ->fetchAllAssociative();
        $snapshots = [];
        foreach ($rows as $row) {
            $snapshots[(int)$row['project']] = Snapshot::fromRow($row);
        }
        return $snapshots;
    }

    public function saveFetchSuccess(int $projectUid, array $payload, int $errorCursor, int $timestamp): void
    {
        $this->save($projectUid, [
            'last_attempt_at' => $timestamp,
            'last_success_at' => $timestamp,
            'failure_reason' => '',
            'payload' => json_encode($payload, self::JSON_FLAGS),
            'error_cursor' => $errorCursor,
        ]);
    }

    public function saveFetchFailure(int $projectUid, string $failureReason, int $timestamp): void
    {
        $this->save($projectUid, [
            'last_attempt_at' => $timestamp,
            'failure_reason' => mb_substr($failureReason, 0, 1024),
        ]);
    }

    public function saveEvaluation(int $projectUid, array $evaluation, int $timestamp): void
    {
        $this->save($projectUid, [
            'evaluation' => json_encode($evaluation, self::JSON_FLAGS),
            'evaluated_at' => $timestamp,
        ]);
    }

    public function removeByProject(int $projectUid): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_NAME)->delete(self::TABLE_NAME, ['project' => $projectUid]);
    }

    private function save(int $projectUid, array $fields): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);
        $affectedRows = $connection->update(self::TABLE_NAME, $fields, ['project' => $projectUid]);
        if ($affectedRows === 0 && $this->findByProject($projectUid) === null) {
            $connection->insert(self::TABLE_NAME, ['project' => $projectUid] + $fields);
        }
    }
}
