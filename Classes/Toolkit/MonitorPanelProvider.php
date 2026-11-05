<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Toolkit;

use Psr\Http\Message\ServerRequestInterface;
use Tenryuubito\WizMonitor\Domain\Repository\ProjectRepository;
use Tenryuubito\WizMonitor\Domain\Repository\SnapshotRepository;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Evaluation\CategoryResult;
use Tenryuubito\WizMonitor\Evaluation\Evaluation;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use WizTech\ToolkitCrm\Domain\Customer;
use WizTech\ToolkitCrm\Domain\Project;
use WizTech\ToolkitCrm\Panel\PanelProviderInterface;
use WizTech\ToolkitCrm\Panel\PanelRenderer;

/**
 * Monitor-Bereich in der Projektansicht des Toolkits.
 */
final readonly class MonitorPanelProvider implements PanelProviderInterface
{
    private const CATEGORY_LABELS = [
        'availability' => 'Verfügbarkeit',
        'security' => 'Sicherheit',
        'errors' => 'Fehler',
        'system' => 'System',
    ];

    public function __construct(
        private ToolkitProjectSync $sync,
        private ProjectRepository $projectRepository,
        private SnapshotRepository $snapshotRepository,
        private PanelRenderer $panelRenderer,
        private UriBuilder $uriBuilder,
    ) {}

    public function getProjectPanels(Project $project, Customer $customer, ServerRequestInterface $request): array
    {
        $monitorUid = $this->sync->findMonitorUid($project->uid);
        $monitor = $monitorUid > 0 ? $this->projectRepository->findByUid($monitorUid) : null;
        $snapshot = $monitor !== null ? $this->snapshotRepository->findByProject($monitor->uid) : null;
        $evaluated = $snapshot !== null && $snapshot->evaluatedAt > 0;
        $evaluation = Evaluation::fromArray($snapshot?->evaluation ?? []);

        return [$this->panelRenderer->render('wiz_monitor', 'MonitorPanel', [
            'project' => $project,
            'monitor' => $monitor,
            'snapshot' => $snapshot,
            'overall' => $evaluated ? $this->status($evaluation->getOverallStatus()) : $this->status(HealthStatus::Unknown),
            'categories' => $evaluated ? array_map(fn(CategoryResult $result): array => [
                'label' => self::CATEGORY_LABELS[$result->category->value] ?? $result->category->value,
                'state' => $this->status($result->status)['state'],
                'stateLabel' => $this->status($result->status)['label'],
            ], $evaluation->getResults()) : [],
            'detailUri' => $monitor !== null ? (string)$this->uriBuilder->buildUriFromRoute('wiz_monitor.detail', ['project' => $monitor->uid]) : '',
            'enableUri' => (string)$this->uriBuilder->buildUriFromRoute('wiz_monitor.enable'),
        ], $request, 30)];
    }

    public function getCustomerPanels(Customer $customer, ServerRequestInterface $request): array
    {
        return [];
    }

    /** @return array{state: string, label: string} */
    private function status(HealthStatus $status): array
    {
        return match ($status) {
            HealthStatus::Ok => ['state' => 'ok', 'label' => 'OK'],
            HealthStatus::Warning => ['state' => 'warning', 'label' => 'Warnung'],
            HealthStatus::Critical => ['state' => 'critical', 'label' => 'Kritisch'],
            HealthStatus::Unknown => ['state' => 'neutral', 'label' => 'Unbekannt'],
        };
    }
}
