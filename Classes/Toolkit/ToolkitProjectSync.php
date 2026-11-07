<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Toolkit;

use Tenryuubito\WizMonitor\Domain\Repository\ProjectRepository;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Verbindet Monitor-Datensätze mit Toolkit-Projekten. Eingerichtet wird im Projekt
 * (monitor_enabled, Prüfseiten, Schlüsselwort, Titel, Basis-URL, Prüfpfade); der Monitor übernimmt alles.
 * Abschalten blendet den Monitor-Datensatz nur aus, damit Verlauf und Secret erhalten bleiben.
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

    /** Schaltet die Überwachung im Projekt ein und legt den Monitor-Datensatz an */
    public function enable(int $toolkitProjectUid): int
    {
        $this->connectionPool->getConnectionForTable(self::TOOLKIT_TABLE)
            ->update(self::TOOLKIT_TABLE, ['monitor_enabled' => 1, 'tstamp' => time()], ['uid' => $toolkitProjectUid]);
        $this->syncFromProject($toolkitProjectUid);
        $monitorUid = $this->findMonitorUid($toolkitProjectUid);
        if ($monitorUid === 0) {
            throw new \RuntimeException('Toolkit project not found', 1793900401);
        }
        return $monitorUid;
    }

    /**
     * Gleicht den Monitor-Datensatz mit dem Projekt ab: anlegen, wenn die Überwachung an ist
     * und noch keiner existiert, sonst Felder und Sichtbarkeit übernehmen.
     */
    public function syncFromProject(int $toolkitProjectUid): void
    {
        $project = $this->findProject($toolkitProjectUid);
        if ($project === null) {
            return;
        }
        $fields = [
            'title' => (string)$project['title'],
            'base_url' => (string)$project['base_url'],
            'check_urls' => (string)$project['check_urls'],
            'check_pages' => (string)($project['monitor_check_pages'] ?? ''),
            'expected_keyword' => (string)($project['monitor_expected_keyword'] ?? ''),
            'hidden' => (bool)($project['monitor_enabled'] ?? false) ? 0 : 1,
            'tstamp' => time(),
        ];
        $connection = $this->connectionPool->getConnectionForTable(ProjectRepository::TABLE_NAME);
        $monitorUid = $this->findMonitorUid($toolkitProjectUid);
        if ($monitorUid > 0) {
            $connection->update(ProjectRepository::TABLE_NAME, $fields, ['uid' => $monitorUid]);
            return;
        }
        if ($fields['hidden'] === 0 && $fields['base_url'] !== '') {
            $connection->insert(ProjectRepository::TABLE_NAME, $fields + [
                'pid' => 0,
                'toolkit_project' => $toolkitProjectUid,
                'crdate' => time(),
            ]);
        }
    }

    /** Entfernt den Monitor-Datensatz, wenn das Projekt gelöscht wird */
    public function removeForProject(int $toolkitProjectUid): void
    {
        $this->connectionPool->getConnectionForTable(ProjectRepository::TABLE_NAME)
            ->update(ProjectRepository::TABLE_NAME, ['deleted' => 1, 'tstamp' => time()], ['toolkit_project' => $toolkitProjectUid]);
    }

    /**
     * Übernimmt Einstellungen bestehender Monitor-Datensätze ins Projekt (einmalig nach Einführung von monitor_enabled).
     * Nur sichtbare Monitore von Projekten, in denen die Überwachung noch aus ist.
     * @return int Anzahl übernommener Projekte
     */
    public function adoptExistingMonitors(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(ProjectRepository::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $monitors = $queryBuilder->select('m.toolkit_project', 'm.check_pages', 'm.expected_keyword')
            ->from(ProjectRepository::TABLE_NAME, 'm')
            ->join('m', self::TOOLKIT_TABLE, 'p', 'p.uid = m.toolkit_project AND p.deleted = 0 AND p.monitor_enabled = 0')
            ->where(
                $queryBuilder->expr()->gt('m.toolkit_project', 0),
                $queryBuilder->expr()->eq('m.hidden', 0),
            )
            ->executeQuery()
            ->fetchAllAssociative();
        $connection = $this->connectionPool->getConnectionForTable(self::TOOLKIT_TABLE);
        foreach ($monitors as $monitor) {
            $connection->update(self::TOOLKIT_TABLE, [
                'monitor_enabled' => 1,
                'monitor_check_pages' => (string)$monitor['check_pages'],
                'monitor_expected_keyword' => (string)$monitor['expected_keyword'],
            ], ['uid' => (int)$monitor['toolkit_project']]);
        }
        return count($monitors);
    }

    /** @return array<string, mixed>|null */
    private function findProject(int $toolkitProjectUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TOOLKIT_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $row = $queryBuilder->select('*')
            ->from(self::TOOLKIT_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($toolkitProjectUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $row;
    }
}
