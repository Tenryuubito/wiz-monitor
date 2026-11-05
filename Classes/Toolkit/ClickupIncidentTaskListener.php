<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Toolkit;

use Psr\Log\LoggerInterface;
use Tenryuubito\WizMonitor\Domain\Repository\IncidentRepository;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Event\IncidentTransitionsEvent;
use Tenryuubito\WizMonitor\Evaluation\Finding;
use Tenryuubito\WizMonitor\Incident\IncidentTransition;
use Tenryuubito\WizMonitor\Notification\NotificationMailer;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use WizTech\ToolkitClickup\Api\ClickUpClient;
use WizTech\ToolkitClickup\Api\ClickUpException;
use WizTech\ToolkitClickup\Api\HierarchyService;
use WizTech\ToolkitClickup\Api\WorkspaceSettings;
use WizTech\ToolkitClickup\Domain\Location;
use WizTech\ToolkitClickup\Domain\ProjectLinkRepository;
use WizTech\ToolkitCrm\Activity\ActivityLog;
use WizTech\ToolkitCrm\Domain\ProjectRepository as ToolkitProjectRepository;

/**
 * Wird ein Incident kritisch, entsteht ein Task in der ClickUp-Liste des Projekts.
 * Ist der Incident behoben, wird der Task geschlossen.
 *
 * Zielliste: die verknüpfte Liste; bei einem verknüpften Folder dessen erste Liste,
 * bei einem Space dessen erste ordnerlose Liste. Fehler bei ClickUp stören die Überwachung nicht.
 */
final readonly class ClickupIncidentTaskListener
{
    public function __construct(
        private ClickUpClient $client,
        private WorkspaceSettings $settings,
        private HierarchyService $hierarchy,
        private ProjectLinkRepository $projectLinks,
        private IncidentRepository $incidentRepository,
        private ToolkitProjectRepository $toolkitProjectRepository,
        private ActivityLog $activityLog,
        private LanguageServiceFactory $languageServiceFactory,
        private LoggerInterface $logger,
    ) {}

    #[AsEventListener(identifier: 'wiz-monitor/toolkit-clickup-incident-task')]
    public function __invoke(IncidentTransitionsEvent $event): void
    {
        $project = $event->project;
        if ($project->toolkitProject === 0 || !$this->client->isConfigured() || $this->settings->getWorkspaceId() === '') {
            return;
        }
        $location = $this->projectLinks->findLocation($project->toolkitProject);
        if ($location === null) {
            return;
        }
        foreach ($event->transitions as $transition) {
            try {
                $this->handle($transition, $project->uid, $project->toolkitProject, $project->title, $project->baseUrl, $location);
            } catch (ClickUpException $e) {
                $this->logger->warning('ClickUp task for incident of monitor project {project} failed: {message}', [
                    'project' => $project->uid,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function handle(IncidentTransition $transition, int $monitorUid, int $toolkitProjectUid, string $title, string $baseUrl, Location $location): void
    {
        $incident = $this->incidentRepository->findLatestForCategory($monitorUid, $transition->category);
        if ($incident === null) {
            return;
        }
        $taskId = (string)($incident['clickup_task_id'] ?? '');
        $language = $this->languageServiceFactory->create('de');
        $categoryLabel = $language->sL(NotificationMailer::LANGUAGE_FILE . ':category.' . $transition->category->value);

        if ($transition->type === IncidentTransition::TYPE_RESOLVED) {
            if ($taskId !== '') {
                $this->closeTask($taskId, $location);
                $this->log($toolkitProjectUid, 'monitor.clickup.closed', sprintf('ClickUp-Task geschlossen: %s wieder in Ordnung', $categoryLabel));
            }
            return;
        }
        if ($transition->status !== HealthStatus::Critical || $taskId !== '') {
            return;
        }
        $listId = $this->resolveTargetList($location);
        if ($listId === null) {
            return;
        }
        $task = $this->client->post('/list/' . $listId . '/task', [
            'name' => sprintf('[Monitor] %s: %s kritisch', $title, $categoryLabel),
            'markdown_content' => $this->describe($transition, $language, $baseUrl),
            'priority' => 1,
            'tags' => ['monitor'],
        ]);
        if (isset($task['id'])) {
            $this->incidentRepository->setClickupTask((int)$incident['uid'], (string)$task['id']);
            $this->log($toolkitProjectUid, 'monitor.clickup.created', sprintf('ClickUp-Task angelegt: %s kritisch', $categoryLabel));
        }
    }

    private function closeTask(string $taskId, Location $location): void
    {
        $listId = $this->resolveTargetList($location);
        $status = $listId !== null ? $this->hierarchy->getClosedStatus($listId) : null;
        if ($status !== null) {
            $this->client->put('/task/' . $taskId, ['status' => $status]);
        }
    }

    private function resolveTargetList(Location $location): ?string
    {
        if ($location->type === 'list') {
            return $location->id;
        }
        foreach ($this->hierarchy->getTree() as $space) {
            if ($location->type === 'space' && $space['id'] === $location->id) {
                return $space['lists'][0]['id'] ?? ($space['folders'][0]['lists'][0]['id'] ?? null);
            }
            foreach ($space['folders'] as $folder) {
                if ($location->type === 'folder' && $folder['id'] === $location->id) {
                    return $folder['lists'][0]['id'] ?? null;
                }
            }
        }
        return null;
    }

    private function describe(IncidentTransition $transition, LanguageService $language, string $baseUrl): string
    {
        $lines = [
            'Der Monitor meldet einen kritischen Zustand für ' . $baseUrl . '.',
            '',
            '**Befunde**',
        ];
        foreach ($transition->findings as $finding) {
            if ($finding instanceof Finding) {
                $text = $language->sL(NotificationMailer::LANGUAGE_FILE . ':finding.' . $finding->key);
                $lines[] = '- ' . ($finding->arguments !== [] ? vsprintf($text, array_map('strval', $finding->arguments)) : $text);
            }
        }
        $lines[] = '';
        $lines[] = 'Dieser Task wurde vom Wiz-Tech Toolkit angelegt und wird automatisch geschlossen, sobald der Zustand wieder in Ordnung ist.';
        return implode("\n", $lines);
    }

    private function log(int $toolkitProjectUid, string $type, string $title): void
    {
        $project = $this->toolkitProjectRepository->findByUid($toolkitProjectUid);
        if ($project !== null) {
            $this->activityLog->add($project->customerUid, $project->uid, $type, $title);
        }
    }
}
