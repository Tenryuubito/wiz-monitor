<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Toolkit;

use WizTech\ToolkitCore\Scheduler\CommandTaskInstaller;
use WizTech\ToolkitCore\Setup\SetupTaskInterface;

final readonly class MonitorSetupTask implements SetupTaskInterface
{
    public function __construct(
        private CommandTaskInstaller $commandTaskInstaller,
        private ToolkitProjectSync $sync,
    ) {}

    public function getPriority(): int
    {
        return 30;
    }

    public function run(): array
    {
        $messages = [$this->commandTaskInstaller->ensure('wizmonitor:run', '*/5 * * * *', 'Monitor: alle überwachten Seiten prüfen')
            ? 'Scheduler-Aufgabe „wizmonitor:run“ angelegt (alle 5 Minuten).'
            : 'Scheduler-Aufgabe „wizmonitor:run“ vorhanden.'];
        $adopted = $this->sync->adoptExistingMonitors();
        if ($adopted > 0) {
            $messages[] = sprintf('Monitor-Einstellungen von %d Projekt(en) ins Projekt übernommen.', $adopted);
        }
        return $messages;
    }
}
