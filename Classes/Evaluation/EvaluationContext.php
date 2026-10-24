<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Evaluation;

use Tenryuubito\WizMonitor\Domain\Model\Project;
use Tenryuubito\WizMonitor\Domain\Model\Snapshot;

final readonly class EvaluationContext
{
    public function __construct(
        public Project $project,
        public int $now,
        public ?Snapshot $snapshot,
        public array $checksByUrl,
        public array $recentErrors,
    ) {}

    public function hasSuccessfulSnapshot(): bool
    {
        return $this->snapshot !== null && $this->snapshot->lastSuccessAt > 0;
    }

    public function getCollectorData(string $collector): ?array
    {
        return $this->hasSuccessfulSnapshot() ? $this->snapshot->getCollectorData($collector) : null;
    }
}
