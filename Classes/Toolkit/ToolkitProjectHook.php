<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Toolkit;

use Tenryuubito\WizMonitor\Domain\Repository\ProjectRepository;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Hält Monitor-Datensätze synchron, wenn ein Toolkit-Projekt angelegt, geändert oder gelöscht wird
 * oder ein Monitor-Datensatz mit einem Projekt verknüpft wird.
 */
final readonly class ToolkitProjectHook
{
    public function __construct(private ToolkitProjectSync $sync) {}

    public function processDatamap_afterDatabaseOperations(string $status, string $table, string|int $id, array $fieldArray, DataHandler $dataHandler): void
    {
        $uid = MathUtility::canBeInterpretedAsInteger($id) ? (int)$id : (int)($dataHandler->substNEWwithIDs[$id] ?? 0);
        if ($uid === 0) {
            return;
        }
        if ($table === ToolkitProjectSync::TOOLKIT_TABLE) {
            $this->sync->syncFromProject($uid);
        }
        if ($table === ProjectRepository::TABLE_NAME && (int)($fieldArray['toolkit_project'] ?? 0) > 0) {
            $this->sync->enable((int)$fieldArray['toolkit_project']);
        }
    }

    public function processCmdmap_postProcess(string $command, string $table, string|int $id, mixed $value, DataHandler $dataHandler): void
    {
        if ($command === 'delete' && $table === ToolkitProjectSync::TOOLKIT_TABLE) {
            $this->sync->removeForProject((int)$id);
        }
    }
}
