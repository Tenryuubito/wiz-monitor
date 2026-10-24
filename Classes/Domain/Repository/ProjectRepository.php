<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Domain\Repository;

use Tenryuubito\WizMonitor\Domain\Model\Project;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;

final readonly class ProjectRepository
{
    public const TABLE_NAME = 'tx_wizmonitor_project';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function findAll(): array
    {
        $queryBuilder = $this->createQueryBuilderIncludingDisabled();
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->orderBy('title')
            ->executeQuery()
            ->fetchAllAssociative();
        return array_map(Project::fromRow(...), $rows);
    }

    public function findActive(): array
    {
        return array_values(array_filter(
            $this->findAll(),
            static fn(Project $project): bool => !$project->disabled,
        ));
    }

    public function findByUid(int $uid): ?Project
    {
        $queryBuilder = $this->createQueryBuilderIncludingDisabled();
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : Project::fromRow($row);
    }

    public function updateEncryptedSecret(int $uid, string $encryptedSecret): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_NAME)->update(
            self::TABLE_NAME,
            ['secret_encrypted' => $encryptedSecret],
            ['uid' => $uid],
        );
    }

    private function createQueryBuilderIncludingDisabled(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        return $queryBuilder;
    }
}
