<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Toolkit;

use Tenryuubito\WizMonitor\Domain\Repository\SnapshotRepository;
use Tenryuubito\WizMonitor\Evaluation\Evaluation;
use Tenryuubito\WizMonitor\Notification\NotificationMailer;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use WizTech\ToolkitCore\Report\ReportSectionProviderInterface;
use WizTech\ToolkitCore\Report\SectionRenderer;

/**
 * Monatsreport: Verfügbarkeit (Anteil erfolgreicher Prüfungen), Vorfälle im Monat, aktueller Sicherheitsstand.
 */
final readonly class MonitorReportSection implements ReportSectionProviderInterface
{
    public function __construct(
        private ToolkitProjectSync $sync,
        private ConnectionPool $connectionPool,
        private SnapshotRepository $snapshots,
        private SectionRenderer $renderer,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function getIdentifier(): string
    {
        return 'monitor';
    }

    public function getLabel(): string
    {
        return 'Verfügbarkeit und Sicherheit';
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function build(array $projectRow, int $from, int $to): ?array
    {
        $monitorUid = $this->sync->findMonitorUid((int)$projectRow['uid']);
        if ($monitorUid === 0) {
            return null;
        }
        $checks = $this->connectionPool->getQueryBuilderForTable('tx_wizmonitor_check');
        $row = $checks->selectLiteral('COUNT(*) AS total', 'COALESCE(SUM(reachable), 0) AS ok', 'AVG(response_time) AS avg_ms', 'MIN(checked_at) AS first_at')
            ->from('tx_wizmonitor_check')
            ->where(
                $checks->expr()->eq('project', $checks->createNamedParameter($monitorUid, Connection::PARAM_INT)),
                $checks->expr()->gte('checked_at', $checks->createNamedParameter($from, Connection::PARAM_INT)),
                $checks->expr()->lte('checked_at', $checks->createNamedParameter($to, Connection::PARAM_INT)),
            )->executeQuery()->fetchAssociative();
        $total = (int)($row['total'] ?? 0);

        $incidentQuery = $this->connectionPool->getQueryBuilderForTable('tx_wizmonitor_incident');
        $incidents = $incidentQuery->select('*')->from('tx_wizmonitor_incident')
            ->where(
                $incidentQuery->expr()->eq('project', $incidentQuery->createNamedParameter($monitorUid, Connection::PARAM_INT)),
                $incidentQuery->expr()->lte('opened_at', $incidentQuery->createNamedParameter($to, Connection::PARAM_INT)),
                $incidentQuery->expr()->or(
                    $incidentQuery->expr()->eq('resolved_at', 0),
                    $incidentQuery->expr()->gte('resolved_at', $incidentQuery->createNamedParameter($from, Connection::PARAM_INT)),
                ),
            )->orderBy('opened_at')->executeQuery()->fetchAllAssociative();
        if ($total === 0 && $incidents === []) {
            return null;
        }
        $language = $this->languageServiceFactory->create('de');
        $label = static fn(string $key): string => $language->sL(NotificationMailer::LANGUAGE_FILE . ':' . $key);
        $snapshot = $this->snapshots->findByProject($monitorUid);
        $security = [];
        if ($snapshot !== null && $snapshot->evaluatedAt > 0) {
            foreach (Evaluation::fromArray($snapshot->evaluation)->getResults() as $result) {
                if ($result->category->value === 'security') {
                    foreach ($result->findings as $finding) {
                        if ($finding->status->isProblem()) {
                            $security[] = $finding->arguments !== [] ? vsprintf($label('finding.' . $finding->key), array_map('strval', $finding->arguments)) : $label('finding.' . $finding->key);
                        }
                    }
                }
            }
        }
        $availability = $total > 0 ? round((int)$row['ok'] / $total * 100, 2) : null;
        $html = $this->renderer->render('wiz_monitor', 'Monitor', [
            'availability' => $availability !== null ? number_format($availability, 2, ',', '.') . ' %' : '–',
            'checks' => $total,
            'avgMs' => (int)round((float)($row['avg_ms'] ?? 0)),
            // Rohdaten werden nur begrenzt aufbewahrt; dann deckt die Messung nicht den ganzen Monat ab
            'coverageNote' => (int)($row['first_at'] ?? 0) > $from + 86400 ? date('d.m.Y', (int)$row['first_at']) : '',
            'incidents' => array_map(static fn(array $incident): array => $incident + [
                'categoryLabel' => $label('category.' . $incident['category']),
                'statusLabel' => $label('status.' . $incident['status']),
            ], $incidents),
            'security' => $security,
        ]);
        return [
            'html' => $html,
            'summary' => ($availability !== null ? number_format($availability, 2, ',', '.') . ' % erreichbar, ' : '')
                . match (count($incidents)) { 0 => 'keine Vorfälle', 1 => '1 Vorfall', default => count($incidents) . ' Vorfälle' },
        ];
    }
}
