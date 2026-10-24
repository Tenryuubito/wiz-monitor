<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Evaluation\Evaluator;

use Tenryuubito\WizMonitor\Enum\HealthCategory;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Evaluation\CategoryEvaluatorInterface;
use Tenryuubito\WizMonitor\Evaluation\EvaluationContext;
use Tenryuubito\WizMonitor\Evaluation\Finding;
use Tenryuubito\WizMonitor\Service\MonitorConfiguration;

final readonly class AvailabilityEvaluator implements CategoryEvaluatorInterface
{
    private const SECONDS_PER_DAY = 86400;
    private const TLS_CRITICAL_DAYS = 7;
    private const TLS_WARNING_DAYS = 21;
    private const KEYWORD_MISSING = 0;

    public function __construct(
        private MonitorConfiguration $monitorConfiguration,
    ) {}

    public function getCategory(): HealthCategory
    {
        return HealthCategory::Availability;
    }

    public function evaluate(EvaluationContext $context): array
    {
        if ($context->checksByUrl === []) {
            return [new Finding(HealthStatus::Unknown, 'availability.no_checks')];
        }
        $findings = [];
        foreach ($context->checksByUrl as $url => $checks) {
            array_push($findings, ...$this->evaluateUrl((string)$url, $checks[0], $checks[1] ?? null, $context));
        }
        return $findings;
    }

    private function evaluateUrl(string $url, array $latest, ?array $previous, EvaluationContext $context): array
    {
        $findings = [];
        $failedRepeatedly = $previous !== null && !(bool)$previous['reachable'];

        if (!(bool)$latest['reachable']) {
            $status = $failedRepeatedly ? HealthStatus::Critical : HealthStatus::Warning;
            $findings[] = (int)$latest['http_status'] > 0
                ? new Finding($status, 'availability.http_status', [$url, (int)$latest['http_status']])
                : new Finding($status, 'availability.unreachable', [$url, (string)$latest['failure_reason']]);
        } else {
            if ((int)$latest['keyword_found'] === self::KEYWORD_MISSING) {
                $keywordMissingRepeatedly = $previous !== null && (int)$previous['keyword_found'] === self::KEYWORD_MISSING;
                $findings[] = new Finding(
                    $keywordMissingRepeatedly ? HealthStatus::Critical : HealthStatus::Warning,
                    'availability.keyword_missing',
                    [$url, $context->project->expectedKeyword],
                );
            }
            if ((int)$latest['response_time'] > $this->monitorConfiguration->getSlowResponseThreshold()) {
                $findings[] = new Finding(HealthStatus::Warning, 'availability.slow', [$url, (int)$latest['response_time']]);
            }
        }

        $tlsExpiresAt = (int)$latest['tls_expires_at'];
        if ($tlsExpiresAt > 0) {
            $remainingDays = (int)floor(($tlsExpiresAt - $context->now) / self::SECONDS_PER_DAY);
            if ($remainingDays < 0) {
                $findings[] = new Finding(HealthStatus::Critical, 'availability.tls_expired', [$url, date('Y-m-d', $tlsExpiresAt)]);
            } elseif ($remainingDays < self::TLS_WARNING_DAYS) {
                $findings[] = new Finding(
                    $remainingDays < self::TLS_CRITICAL_DAYS ? HealthStatus::Critical : HealthStatus::Warning,
                    'availability.tls_expiring',
                    [$url, $remainingDays],
                );
            }
        }
        return $findings;
    }
}
