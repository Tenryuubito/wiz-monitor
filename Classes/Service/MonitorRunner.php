<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Service;

use Psr\Log\LoggerInterface;
use Tenryuubito\WizMonitor\Check\HttpProbe;
use Tenryuubito\WizMonitor\Check\StatusFetcher;
use Tenryuubito\WizMonitor\Domain\Model\Project;
use Tenryuubito\WizMonitor\Domain\Repository\CheckRepository;
use Tenryuubito\WizMonitor\Domain\Repository\ErrorRepository;
use Tenryuubito\WizMonitor\Domain\Repository\IncidentRepository;
use Tenryuubito\WizMonitor\Domain\Repository\ProjectRepository;
use Tenryuubito\WizMonitor\Domain\Repository\SnapshotRepository;
use Tenryuubito\WizMonitor\Evaluation\Evaluation;
use Tenryuubito\WizMonitor\Evaluation\EvaluationContext;
use Tenryuubito\WizMonitor\Evaluation\ProjectEvaluator;
use Tenryuubito\WizMonitor\Incident\IncidentManager;
use Tenryuubito\WizMonitor\Notification\NotificationMailer;

final readonly class MonitorRunner
{
    private const CHECKS_PER_URL = 2;
    private const RECENT_ERROR_SECONDS = 86400;
    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        private ProjectRepository $projectRepository,
        private CheckRepository $checkRepository,
        private SnapshotRepository $snapshotRepository,
        private ErrorRepository $errorRepository,
        private IncidentRepository $incidentRepository,
        private HttpProbe $httpProbe,
        private StatusFetcher $statusFetcher,
        private ProjectEvaluator $projectEvaluator,
        private IncidentManager $incidentManager,
        private NotificationMailer $notificationMailer,
        private MonitorConfiguration $monitorConfiguration,
        private CheckTargetResolver $checkTargetResolver,
        private LoggerInterface $logger,
    ) {}

    public function runAll(): array
    {
        $evaluations = [];
        foreach ($this->projectRepository->findActive() as $project) {
            $evaluations[$project->uid] = $this->runSafely($project);
        }
        $this->removeExpiredData();
        return $evaluations;
    }

    public function runProject(Project $project): ?Evaluation
    {
        return $this->runSafely($project);
    }

    private function runSafely(Project $project): ?Evaluation
    {
        try {
            return $this->run($project);
        } catch (\Throwable $throwable) {
            $this->logger->error('Monitoring run for project {project} failed: {message}', [
                'project' => $project->uid,
                'message' => $throwable->getMessage(),
                'exception' => $throwable,
            ]);
            return null;
        }
    }

    private function run(Project $project): Evaluation
    {
        $newErrors = $this->synchronizeStatus($project);
        $snapshot = $this->snapshotRepository->findByProject($project->uid);
        $checkUrls = $this->checkTargetResolver->resolveUrls($project, $snapshot);
        foreach ($checkUrls as $url) {
            $this->checkRepository->add($project->uid, $this->httpProbe->probe($url, $project->expectedKeyword), time());
        }

        $now = time();
        $evaluation = $this->projectEvaluator->evaluate(new EvaluationContext(
            project: $project,
            now: $now,
            snapshot: $snapshot,
            checksByUrl: $this->checkRepository->findRecentGroupedByUrl($project->uid, $checkUrls, self::CHECKS_PER_URL),
            recentErrors: $this->errorRepository->findSeenSince($project->uid, $now - self::RECENT_ERROR_SECONDS),
        ));
        $this->snapshotRepository->saveEvaluation($project->uid, $evaluation->toArray(), $now);

        $transitions = $this->incidentManager->apply($project, $evaluation, $now);
        try {
            $this->notificationMailer->send($project, $transitions, $newErrors);
        } catch (\Throwable $throwable) {
            $this->logger->error('Monitoring notification for project {project} could not be sent: {message}', [
                'project' => $project->uid,
                'message' => $throwable->getMessage(),
                'exception' => $throwable,
            ]);
        }
        return $evaluation;
    }

    private function synchronizeStatus(Project $project): array
    {
        if (!$project->isSecretConfigured()) {
            return [];
        }
        $snapshot = $this->snapshotRepository->findByProject($project->uid);
        $result = $this->statusFetcher->fetch($project, $snapshot?->errorCursor ?? 0);
        if (!$result->succeeded) {
            $this->snapshotRepository->saveFetchFailure($project->uid, $result->failureReason, time());
            return [];
        }
        $this->snapshotRepository->saveFetchSuccess($project->uid, $result->payload, $result->errorCursor, time());
        return $this->errorRepository->synchronize($project->uid, $result->errorItems);
    }

    private function removeExpiredData(): void
    {
        $expiredBefore = time() - $this->monitorConfiguration->getRetentionDays() * self::SECONDS_PER_DAY;
        $this->checkRepository->removeCheckedBefore($expiredBefore);
        $this->errorRepository->removeLastSeenBefore($expiredBefore);
        $this->incidentRepository->removeResolvedBefore($expiredBefore);
    }
}
