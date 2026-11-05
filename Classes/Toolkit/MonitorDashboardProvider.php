<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Toolkit;

use Psr\Http\Message\ServerRequestInterface;
use Tenryuubito\WizMonitor\Domain\Repository\ProjectRepository;
use Tenryuubito\WizMonitor\Domain\Repository\SnapshotRepository;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Evaluation\Evaluation;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use WizTech\ToolkitCore\Dashboard\DashboardProviderInterface;
use WizTech\ToolkitCore\Dashboard\Kpi;
use WizTech\ToolkitCore\Dashboard\Todo;

final readonly class MonitorDashboardProvider implements DashboardProviderInterface
{
    public function __construct(
        private ProjectRepository $projects,
        private SnapshotRepository $snapshots,
        private UriBuilder $uriBuilder,
    ) {}

    public function getKpis(ServerRequestInterface $request): array
    {
        $projects = $this->projects->findActive();
        if ($projects === []) {
            return [];
        }
        $problems = count(array_filter($this->statuses(), static fn(HealthStatus $status): bool => $status->isProblem()));
        return [new Kpi('Monitor', $problems === 0 ? 'alles OK' : (string)$problems, $problems === 0 ? (count($projects) === 1 ? '1 Seite überwacht' : count($projects) . ' Seiten überwacht') : 'Seiten mit Warnung oder kritisch', (string)$this->uriBuilder->buildUriFromRoute('wiz_monitor'), 14)];
    }

    public function getTodos(ServerRequestInterface $request): array
    {
        $todos = [];
        foreach ($this->projects->findActive() as $project) {
            $status = $this->statuses()[$project->uid] ?? HealthStatus::Unknown;
            if ($status === HealthStatus::Critical) {
                $todos[] = new Todo(sprintf('%s: kritisch', $project->title), $project->baseUrl, 'critical', 'Monitor', (string)$this->uriBuilder->buildUriFromRoute('wiz_monitor.detail', ['project' => $project->uid]), 1);
            }
        }
        return $todos;
    }

    public function getSections(ServerRequestInterface $request): array
    {
        return [];
    }

    /** @return array<int, HealthStatus> */
    private function statuses(): array
    {
        $statuses = [];
        foreach ($this->snapshots->findAllIndexedByProject() as $projectUid => $snapshot) {
            if ($snapshot->evaluatedAt > 0) {
                $statuses[$projectUid] = Evaluation::fromArray($snapshot->evaluation)->getOverallStatus();
            }
        }
        return $statuses;
    }
}
