<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Evaluation\Evaluator;

use Tenryuubito\WizMonitor\Enum\HealthCategory;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Evaluation\CategoryEvaluatorInterface;
use Tenryuubito\WizMonitor\Evaluation\EvaluationContext;
use Tenryuubito\WizMonitor\Evaluation\Finding;

final readonly class SystemEvaluator implements CategoryEvaluatorInterface
{
    private const STALE_SNAPSHOT_SECONDS = 900;
    private const SCHEDULER_OVERDUE_SECONDS = 3600;
    private const DISK_CRITICAL_PERCENT = 5;
    private const DISK_WARNING_PERCENT = 10;
    private const DATE_TIME_FORMAT = 'Y-m-d H:i';

    public function getCategory(): HealthCategory
    {
        return HealthCategory::System;
    }

    public function evaluate(EvaluationContext $context): array
    {
        $snapshot = $context->snapshot;
        if (!$context->project->isSecretConfigured()) {
            return [new Finding(HealthStatus::Unknown, 'system.no_secret')];
        }
        if ($snapshot === null || $snapshot->lastAttemptAt === 0) {
            return [new Finding(HealthStatus::Unknown, 'system.no_data')];
        }
        if ($snapshot->lastSuccessAt === 0) {
            return [new Finding(HealthStatus::Critical, 'system.status_endpoint_unreachable', [$snapshot->failureReason])];
        }

        $findings = [];
        if ($snapshot->lastAttemptAt > $snapshot->lastSuccessAt) {
            $findings[] = new Finding(
                $context->now - $snapshot->lastSuccessAt > self::STALE_SNAPSHOT_SECONDS ? HealthStatus::Critical : HealthStatus::Warning,
                'system.status_endpoint_failed',
                [$snapshot->failureReason, date(self::DATE_TIME_FORMAT, $snapshot->lastSuccessAt)],
            );
        }
        foreach ($snapshot->getFailedCollectors() as $collector => $message) {
            $findings[] = new Finding(HealthStatus::Warning, 'system.collector_failed', [$collector, $message]);
        }

        $environment = $context->getCollectorData('environment') ?? [];
        if (str_starts_with((string)($environment['applicationContext'] ?? ''), 'Development')) {
            $findings[] = new Finding(HealthStatus::Warning, 'system.development_context', [(string)$environment['applicationContext']]);
        }

        array_push(
            $findings,
            ...$this->evaluateStorage($context->getCollectorData('storage')),
            ...$this->evaluateScheduler($context->getCollectorData('scheduler'), (int)($snapshot->payload['generatedAt'] ?? $context->now)),
        );
        return $findings;
    }

    private function evaluateStorage(?array $storage): array
    {
        if ($storage === null) {
            return [];
        }
        $findings = [];
        if (($storage['varPathWritable'] ?? true) === false) {
            $findings[] = new Finding(HealthStatus::Critical, 'system.var_not_writable');
        }
        $totalBytes = (int)($storage['totalBytes'] ?? 0);
        if ($totalBytes > 0) {
            $freePercent = (int)floor((int)($storage['freeBytes'] ?? 0) / $totalBytes * 100);
            if ($freePercent < self::DISK_WARNING_PERCENT) {
                $findings[] = new Finding(
                    $freePercent < self::DISK_CRITICAL_PERCENT ? HealthStatus::Critical : HealthStatus::Warning,
                    'system.disk_space',
                    [$freePercent],
                );
            }
        }
        return $findings;
    }

    private function evaluateScheduler(?array $scheduler, int $generatedAt): array
    {
        if ($scheduler === null || ($scheduler['installed'] ?? false) !== true) {
            return [];
        }
        $findings = [];
        foreach (is_array($scheduler['tasks'] ?? null) ? $scheduler['tasks'] : [] as $task) {
            if (!is_array($task) || ($task['disabled'] ?? false) === true) {
                continue;
            }
            $label = (string)(($task['description'] ?? '') ?: ($task['type'] ?? '')) . ' [' . (int)($task['uid'] ?? 0) . ']';
            if (($task['failed'] ?? false) === true) {
                $findings[] = new Finding(HealthStatus::Warning, 'system.scheduler_task_failed', [$label]);
            }
            $nextExecution = (int)($task['nextExecution'] ?? 0);
            if ($nextExecution > 0 && $nextExecution < $generatedAt - self::SCHEDULER_OVERDUE_SECONDS) {
                $findings[] = new Finding(HealthStatus::Warning, 'system.scheduler_task_overdue', [$label, date(self::DATE_TIME_FORMAT, $nextExecution)]);
            }
        }
        return $findings;
    }
}
