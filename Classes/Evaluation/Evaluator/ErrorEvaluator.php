<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Evaluation\Evaluator;

use Psr\Log\LogLevel;
use Tenryuubito\WizMonitor\Enum\HealthCategory;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Evaluation\CategoryEvaluatorInterface;
use Tenryuubito\WizMonitor\Evaluation\EvaluationContext;
use Tenryuubito\WizMonitor\Evaluation\Finding;

final readonly class ErrorEvaluator implements CategoryEvaluatorInterface
{
    private const CRITICAL_LEVELS = [LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY];
    private const MAXIMUM_MESSAGE_LENGTH = 200;

    public function getCategory(): HealthCategory
    {
        return HealthCategory::Errors;
    }

    public function evaluate(EvaluationContext $context): array
    {
        if (!$context->hasSuccessfulSnapshot()) {
            return [new Finding(HealthStatus::Unknown, 'errors.no_data')];
        }
        if ($context->recentErrors === []) {
            return [];
        }

        $criticalErrors = array_values(array_filter(
            $context->recentErrors,
            static fn(array $error): bool => in_array((string)$error['level'], self::CRITICAL_LEVELS, true),
        ));
        $relevantErrors = $criticalErrors !== [] ? $criticalErrors : $context->recentErrors;
        $latestMessage = mb_substr((string)$relevantErrors[0]['message'], 0, self::MAXIMUM_MESSAGE_LENGTH);

        return [
            $criticalErrors !== []
                ? new Finding(HealthStatus::Critical, 'errors.recent_critical', [count($criticalErrors), $latestMessage])
                : new Finding(HealthStatus::Warning, 'errors.recent', [count($relevantErrors), $latestMessage]),
        ];
    }
}
