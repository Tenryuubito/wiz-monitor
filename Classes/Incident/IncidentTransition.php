<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Incident;

use Tenryuubito\WizMonitor\Enum\HealthCategory;
use Tenryuubito\WizMonitor\Enum\HealthStatus;

final readonly class IncidentTransition
{
    public const TYPE_OPENED = 'opened';
    public const TYPE_CHANGED = 'changed';
    public const TYPE_RESOLVED = 'resolved';

    public function __construct(
        public HealthCategory $category,
        public string $type,
        public HealthStatus $previousStatus,
        public HealthStatus $status,
        public array $findings,
    ) {}
}
