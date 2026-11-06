<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Toolkit;

use Tenryuubito\WizMonitor\Domain\Repository\SnapshotRepository;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Evaluation\Evaluation;
use Tenryuubito\WizMonitor\Evaluation\Finding;
use Tenryuubito\WizMonitor\Notification\NotificationMailer;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use WizTech\ToolkitCore\Report\ReportFormat;
use WizTech\ToolkitCore\Report\ReportSectionProviderInterface;
use WizTech\ToolkitCore\Report\ReportStatus;
use WizTech\ToolkitCore\Report\SectionRenderer;

/**
 * Monatsreport: Erreichbarkeit als Ausfallzeit, Störungen mit Dauer, Sicherheitsstand in Alltagssprache.
 */
final readonly class MonitorReportSection implements ReportSectionProviderInterface
{
    /** Kundentexte für Sicherheitsbefunde; Platzhalter wie in den Monitor-Sprachdateien */
    private const SECURITY_TEXTS = [
        'security.advisory' => ['In der Erweiterung %1$s ist eine Sicherheitslücke bekannt', 'Ein Update der Erweiterung schließt die Lücke.'],
        'security.typo3_security_update' => ['Für das Redaktionssystem TYPO3 steht ein Sicherheitsupdate aus (%1$s → %2$s)', 'Sicherheitsupdates schließen bekannte Lücken und sollten zeitnah eingespielt werden.'],
        'security.typo3_update' => ['Für TYPO3 ist ein reguläres Update verfügbar (%1$s → %2$s)', 'Wir spielen es mit der nächsten Wartung ein.'],
        'security.typo3_elts_only' => ['TYPO3 %1$s wird seit %2$s nur noch über kostenpflichtigen Langzeit-Support abgesichert', 'Ein Upgrade auf eine aktuelle Version sollte geplant werden.'],
        'security.typo3_end_of_life' => ['TYPO3 %1$s erhält seit %2$s keine Sicherheitsupdates mehr', 'Neu entdeckte Lücken werden nicht mehr geschlossen. Ein Upgrade ist dringend.'],
        'security.php_end_of_life' => ['Die Server-Software PHP %1$s erhält seit %2$s keine Sicherheitsupdates mehr', 'Die Umstellung auf eine aktuelle PHP-Version ist dringend.'],
        'security.php_end_of_life_soon' => ['Die Server-Software PHP %1$s erhält nur noch bis %2$s Sicherheitsupdates', 'Die Umstellung sollte rechtzeitig eingeplant werden.'],
        'security.install_tool_enabled' => ['Eine Schutzeinstellung der Website ist nicht optimal gesetzt', 'Wir passen die Einstellung an; für Sie entsteht kein Aufwand.'],
        'security.display_errors' => ['Eine Schutzeinstellung der Website ist nicht optimal gesetzt', 'Wir passen die Einstellung an; für Sie entsteht kein Aufwand.'],
        'security.trusted_hosts' => ['Eine Schutzeinstellung der Website ist nicht optimal gesetzt', 'Wir passen die Einstellung an; für Sie entsteht kein Aufwand.'],
        'security.failed_logins' => ['Auffällig viele fehlgeschlagene Anmeldeversuche (%1$s in 24 Stunden)', 'Das deutet auf automatisierte Angriffsversuche hin. Wir beobachten das und sichern den Zugang bei Bedarf zusätzlich ab.'],
    ];

    /** Störungsarten in Alltagssprache */
    private const INCIDENT_LABELS = [
        'availability' => 'Website nicht oder nur eingeschränkt erreichbar',
        'security' => 'Sicherheitsproblem',
        'errors' => 'Technische Fehler auf der Website',
        'system' => 'Störung im technischen Hintergrund',
    ];

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
        return 'Erreichbarkeit und Sicherheit';
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
        $row = $checks->selectLiteral('COUNT(*) AS total', 'COALESCE(SUM(reachable), 0) AS ok', 'MIN(checked_at) AS first_at', 'MAX(checked_at) AS last_at')
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

        // Erreichbarkeit als Anteil erfolgreicher Prüfungen, hochgerechnet auf den gemessenen Zeitraum
        $availability = $total > 0 ? (int)$row['ok'] / $total * 100 : null;
        $measuredFrom = max($from, (int)($row['first_at'] ?? $from));
        $measuredTo = min($to, max((int)($row['last_at'] ?? $to), $measuredFrom));
        $downtime = $availability !== null ? (int)round((100 - $availability) / 100 * ($measuredTo - $measuredFrom)) : 0;
        $availabilityStatus = match (true) {
            $availability === null => ReportStatus::OK,
            $availability < 99.0 => ReportStatus::CRITICAL,
            $availability < 99.9 => ReportStatus::WARNING,
            default => ReportStatus::OK,
        };

        $incidentRows = [];
        $resolvedSecurity = 0;
        $openIncidents = 0;
        foreach ($incidents as $incident) {
            $resolvedAt = (int)$incident['resolved_at'];
            $open = $resolvedAt === 0 || $resolvedAt > $to;
            $openIncidents += $open ? 1 : 0;
            if (!$open && $incident['category'] === 'security') {
                $resolvedSecurity++;
            }
            $incidentRows[] = [
                'label' => self::INCIDENT_LABELS[$incident['category']] ?? 'Störung',
                'serious' => $incident['status'] === 'critical',
                'opened' => (int)$incident['opened_at'],
                'duration' => $open ? '' : ReportFormat::span($resolvedAt - (int)$incident['opened_at']),
            ];
        }

        // Sicherheitsstand aus der letzten Auswertung
        $language = $this->languageServiceFactory->create('de');
        $label = static fn(string $key): string => $language->sL(NotificationMailer::LANGUAGE_FILE . ':' . $key);
        $snapshot = $this->snapshots->findByProject($monitorUid);
        $security = [];
        $securityStatus = ReportStatus::OK;
        $software = [];
        if ($snapshot !== null && $snapshot->evaluatedAt > 0) {
            foreach (Evaluation::fromArray($snapshot->evaluation)->getResults() as $result) {
                if ($result->category->value !== 'security') {
                    continue;
                }
                foreach ($result->findings as $finding) {
                    if (!$finding->status->isProblem()) {
                        continue;
                    }
                    [$title, $text] = $this->describe($finding, $label);
                    $status = $finding->status === HealthStatus::Critical ? ReportStatus::CRITICAL : ReportStatus::WARNING;
                    // gleichartige Befunde (z. B. mehrere Schutzeinstellungen) nur einmal nennen
                    $security[$title] = ['title' => $title, 'text' => $text, 'status' => ReportStatus::worst($status, $security[$title]['status'] ?? ReportStatus::OK)];
                    $securityStatus = ReportStatus::worst($securityStatus, $status);
                }
            }
            $environment = is_array($snapshot->payload['environment'] ?? null) ? $snapshot->payload['environment'] : [];
            if (($environment['typo3Version'] ?? '') !== '') {
                $software[] = 'TYPO3 ' . $environment['typo3Version'];
            }
            if (($environment['phpVersion'] ?? '') !== '') {
                $software[] = 'PHP ' . $environment['phpVersion'];
            }
        }
        $security = array_values($security);
        $status = ReportStatus::worst($availabilityStatus, $securityStatus, $openIncidents > 0 ? ReportStatus::WARNING : ReportStatus::OK);

        $actions = $security;
        if ($availabilityStatus !== ReportStatus::OK) {
            $actions[] = [
                'title' => 'Ursache der Ausfälle beheben',
                'text' => sprintf('Die Website war insgesamt etwa %s nicht erreichbar. Wir prüfen Server und Hosting, damit das nicht wieder vorkommt.', ReportFormat::span($downtime)),
                'status' => $availabilityStatus,
            ];
        }

        $availabilityText = $availability !== null ? ReportFormat::percent(floor($availability * 100) / 100, 2) : '–';
        $html = $this->renderer->render('wiz_monitor', 'Monitor', [
            'availability' => $availabilityText,
            'downtime' => $availability !== null && $downtime >= 60 ? ReportFormat::span($downtime) : '',
            'coverageNote' => $measuredFrom > $from + 86400 ? date('d.m.Y', $measuredFrom) : '',
            'incidents' => $incidentRows,
            'resolvedSecurity' => $resolvedSecurity,
            'security' => $security,
            'securityDate' => $snapshot !== null && $snapshot->evaluatedAt > 0 ? date('d.m.Y', $snapshot->evaluatedAt) : '',
            'software' => implode(' · ', $software),
        ]);
        return [
            'html' => $html,
            'summary' => ($availability !== null ? $availabilityText . ' erreichbar, ' : '')
                . match (count($incidents)) { 0 => 'keine Störungen', 1 => '1 Störung', default => count($incidents) . ' Störungen' }
                . ($security === [] ? ', Sicherheit aktuell' : ', ' . ReportFormat::count(count($security), 'offener Sicherheitspunkt', 'offene Sicherheitspunkte')),
            'status' => $status,
            'headlines' => array_values(array_filter([
                $availability !== null ? [
                    'label' => 'Erreichbarkeit',
                    'value' => $availabilityText,
                    'note' => $downtime >= 60 ? 'ca. ' . ReportFormat::span($downtime) . ' Ausfall' : 'ohne nennenswerten Ausfall',
                    'status' => $availabilityStatus,
                ] : null,
                $snapshot !== null && $snapshot->evaluatedAt > 0 ? [
                    'label' => 'Sicherheit',
                    'value' => $security === [] ? 'aktuell' : ReportFormat::count(count($security), 'Punkt', 'Punkte'),
                    'note' => $security === [] ? 'keine bekannten Lücken' : 'offen, siehe Empfehlungen',
                    'status' => $securityStatus,
                ] : null,
            ])),
            'actions' => array_values($actions),
        ];
    }

    /** @return array{0: string, 1: string} Titel und Erklärung für den Kunden */
    private function describe(Finding $finding, \Closure $label): array
    {
        $arguments = array_map('strval', $finding->arguments);
        if (isset(self::SECURITY_TEXTS[$finding->key])) {
            [$title, $text] = self::SECURITY_TEXTS[$finding->key];
            preg_match_all('/%(\d)\$s/', $title, $matches);
            $needed = $matches[1] === [] ? 0 : max(array_map('intval', $matches[1]));
            return [$needed > 0 ? vsprintf($title, array_pad($arguments, $needed, '')) : $title, $text];
        }
        $title = $label('finding.' . $finding->key);
        return [$arguments !== [] ? vsprintf($title, $arguments) : $title, ''];
    }
}
