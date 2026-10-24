<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Incident;

use Tenryuubito\WizMonitor\Domain\Model\Project;
use Tenryuubito\WizMonitor\Domain\Repository\IncidentRepository;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Evaluation\Evaluation;
use Tenryuubito\WizMonitor\Evaluation\Finding;

final readonly class IncidentManager
{
    public function __construct(
        private IncidentRepository $incidentRepository,
    ) {}

    public function apply(Project $project, Evaluation $evaluation, int $timestamp): array
    {
        $transitions = [];
        foreach ($evaluation->getResults() as $result) {
            $openIncident = $this->incidentRepository->findOpen($project->uid, $result->category);
            $problemFindings = array_values(array_filter(
                $result->findings,
                static fn(Finding $finding): bool => $finding->status->isProblem(),
            ));
            $serializedFindings = array_map(static fn(Finding $finding): array => $finding->toArray(), $problemFindings);

            if ($result->status->isProblem()) {
                if ($openIncident === null) {
                    $this->incidentRepository->open($project->uid, $result->category, $result->status, $serializedFindings, $timestamp);
                    $transitions[] = new IncidentTransition($result->category, IncidentTransition::TYPE_OPENED, HealthStatus::Ok, $result->status, $problemFindings);
                    continue;
                }
                $previousStatus = HealthStatus::tryFrom((string)$openIncident['status']) ?? HealthStatus::Unknown;
                $this->incidentRepository->update((int)$openIncident['uid'], $result->status, $serializedFindings, $timestamp);
                if ($previousStatus !== $result->status) {
                    $transitions[] = new IncidentTransition($result->category, IncidentTransition::TYPE_CHANGED, $previousStatus, $result->status, $problemFindings);
                }
                continue;
            }

            if ($result->status === HealthStatus::Ok && $openIncident !== null) {
                $this->incidentRepository->resolve((int)$openIncident['uid'], $timestamp);
                $transitions[] = new IncidentTransition(
                    $result->category,
                    IncidentTransition::TYPE_RESOLVED,
                    HealthStatus::tryFrom((string)$openIncident['status']) ?? HealthStatus::Unknown,
                    HealthStatus::Ok,
                    [],
                );
            }
        }
        return $transitions;
    }
}
