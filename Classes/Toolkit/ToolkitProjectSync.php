<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Toolkit;

use Tenryuubito\WizMonitor\Domain\Repository\ProjectRepository;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Verbindet Monitor-Datensätze mit Toolkit-Projekten. Titel, Basis-URL und Prüfpfade
 * pflegt man im Projekt; der Monitor übernimmt sie.
 */
final readonly class ToolkitProjectSync
{
    public const TOOLKIT_TABLE = 'tx_toolkit_project';

    public function __construct(private ConnectionPool $connectionPool) {}

    /** UID des Monitor-Datensatzes zu einem Toolkit-Projekt, 0 wenn keiner existiert */
    public function findMonitorUid(int $toolkitProjectUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(ProjectRepository::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        return (int)$queryBuilder->select('uid')
            ->from(ProjectRepository::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('toolkit_project', $queryBuilder->createNamedParameter($toolkitProjectUid, Connection::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
    }

    /** Legt den Monitor-Datensatz für ein Projekt an, falls noch keiner existiert */
    public function enable(int $toolkitProjectUid): int
    {
        $existing = $this->findMonitorUid($toolkitProjectUid);
        if ($existing > 0) {
            return $existing;
        }
        $fields = $this->fieldsFromProject($toolkitProjectUid);
        if ($fields === null) {
            throw new \RuntimeException('Toolkit project not found', 1793900401);
        }
        $connection = $this->connectionPool->getConnectionForTable(ProjectRepository::TABLE_NAME);
        $connection->insert(ProjectRepository::TABLE_NAME, $fields + [
            'pid' => 0,
            'toolkit_project' => $toolkitProjectUid,
            'crdate' => time(),
        ]);
        return (int)$connection->lastInsertId();
    }

    /** Übernimmt Titel, Basis-URL und Prüfpfade aus dem Projekt in den Monitor-Datensatz */
    public function syncFromProject(int $toolkitProjectUid): void
    {
        $monitorUid = $this->findMonitorUid($toolkitProjectUid);
        $fields = $this->fieldsFromProject($toolkitProjectUid);
        if ($monitorUid === 0 || $fields === null) {
            return;
        }
        $this->connectionPool->getConnectionForTable(ProjectRepository::TABLE_NAME)
            ->update(ProjectRepository::TABLE_NAME, $fields, ['uid' => $monitorUid]);
    }

    /** Entfernt den Monitor-Datensatz, wenn das Projekt gelöscht wird */
    public function removeForProject(int $toolkitProjectUid): void
    {
        $this->connectionPool->getConnectionForTable(ProjectRepository::TABLE_NAME)
            ->update(ProjectRepository::TABLE_NAME, ['deleted' => 1, 'tstamp' => time()], ['toolkit_project' => $toolkitProjectUid]);
    }

    /** @return array<string, mixed>|null */
    private function fieldsFromProject(int $toolkitProjectUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TOOLKIT_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $row = $queryBuilder->select('title', 'base_url', 'check_urls')
            ->from(self::TOOLKIT_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($toolkitProjectUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false) {
            return null;
        }
        return [
            'title' => (string)$row['title'],
            'base_url' => (string)$row['base_url'],
            'check_urls' => (string)$row['check_urls'],
            'tstamp' => time(),
        ];
    }
}
