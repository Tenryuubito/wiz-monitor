<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Event;

use Tenryuubito\WizMonitor\Domain\Model\Project;
use Tenryuubito\WizMonitor\Incident\IncidentTransition;

/**
 * Wird nach jeder Auswertung ausgelöst, wenn sich Incidents eines Projekts geändert haben
 * (geöffnet, Schwere geändert, behoben).
 */
final readonly class IncidentTransitionsEvent
{
    /** @param IncidentTransition[] $transitions */
    public function __construct(
        public Project $project,
        public array $transitions,
        public int $timestamp,
    ) {}
}
