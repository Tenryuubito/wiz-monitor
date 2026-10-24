<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Evaluation\Evaluator;

use Tenryuubito\WizMonitor\Enum\HealthCategory;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Evaluation\CategoryEvaluatorInterface;
use Tenryuubito\WizMonitor\Evaluation\EvaluationContext;
use Tenryuubito\WizMonitor\Evaluation\Finding;
use Tenryuubito\WizMonitor\External\AdvisoryChecker;
use Tenryuubito\WizMonitor\External\LifecycleChecker;

final readonly class SecurityEvaluator implements CategoryEvaluatorInterface
{
    private const PHP_END_OF_LIFE_WARNING_SECONDS = 90 * 86400;
    private const FAILED_LOGIN_WARNING_THRESHOLD = 20;
    private const DISPLAY_ERRORS_ENABLED = 1;
    private const DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private AdvisoryChecker $advisoryChecker,
        private LifecycleChecker $lifecycleChecker,
    ) {}

    public function getCategory(): HealthCategory
    {
        return HealthCategory::Security;
    }

    public function evaluate(EvaluationContext $context): array
    {
        $packages = $context->getCollectorData('packages');
        $environment = $context->getCollectorData('environment');
        if ($packages === null || $environment === null) {
            return [new Finding(HealthStatus::Unknown, 'security.no_data')];
        }

        return [
            ...$this->evaluateAdvisories(is_array($packages['packages'] ?? null) ? $packages['packages'] : []),
            ...$this->evaluateTypo3((string)($environment['typo3Version'] ?? ''), $context->now),
            ...$this->evaluatePhp((string)($environment['phpVersion'] ?? ''), $context->now),
            ...$this->evaluateHardening($context->getCollectorData('hardening'), (string)($environment['applicationContext'] ?? '')),
        ];
    }

    private function evaluateAdvisories(array $packages): array
    {
        $advisories = $this->advisoryChecker->findAffectingAdvisories($packages);
        if ($advisories === null) {
            return [new Finding(HealthStatus::Unknown, 'security.lookup_failed', ['packagist.org'])];
        }
        return array_map(
            static fn(array $advisory): Finding => new Finding(
                HealthStatus::Critical,
                'security.advisory',
                [$advisory['package'], $advisory['version'], $advisory['identifier'], $advisory['title']],
            ),
            $advisories,
        );
    }

    private function evaluateTypo3(string $installedVersion, int $now): array
    {
        if ($installedVersion === '') {
            return [new Finding(HealthStatus::Unknown, 'security.no_data')];
        }
        $majorVersion = (int)explode('.', $installedVersion)[0];
        $findings = [];

        $latestSecurityVersion = $this->lifecycleChecker->getTypo3LatestSecurityVersion($majorVersion);
        $latestVersion = $this->lifecycleChecker->getTypo3LatestVersion($majorVersion);
        if ($latestSecurityVersion === null || $latestVersion === null) {
            $findings[] = new Finding(HealthStatus::Unknown, 'security.lookup_failed', ['get.typo3.org']);
        } elseif (version_compare($installedVersion, $latestSecurityVersion, '<')) {
            $findings[] = new Finding(HealthStatus::Critical, 'security.typo3_security_update', [$installedVersion, $latestSecurityVersion]);
        } elseif (version_compare($installedVersion, $latestVersion, '<')) {
            $findings[] = new Finding(HealthStatus::Warning, 'security.typo3_update', [$installedVersion, $latestVersion]);
        }

        $lifecycle = $this->lifecycleChecker->getTypo3MajorLifecycle($majorVersion);
        if ($lifecycle !== null && $lifecycle['maintainedUntil'] > 0 && $lifecycle['maintainedUntil'] < $now) {
            $findings[] = $lifecycle['eltsUntil'] > $now
                ? new Finding(HealthStatus::Warning, 'security.typo3_elts_only', [$majorVersion, date(self::DATE_FORMAT, $lifecycle['maintainedUntil'])])
                : new Finding(HealthStatus::Critical, 'security.typo3_end_of_life', [$majorVersion, date(self::DATE_FORMAT, max($lifecycle['eltsUntil'], $lifecycle['maintainedUntil']))]);
        }
        return $findings;
    }

    private function evaluatePhp(string $phpVersion, int $now): array
    {
        if ($phpVersion === '') {
            return [];
        }
        $cycle = $this->lifecycleChecker->getPhpCycle($phpVersion);
        if ($cycle === null) {
            return [new Finding(HealthStatus::Unknown, 'security.lookup_failed', ['endoflife.date'])];
        }
        $securitySupportUntil = $cycle['securitySupportUntil'];
        if ($securitySupportUntil === 0) {
            return [];
        }
        if ($securitySupportUntil < $now) {
            return [new Finding(HealthStatus::Critical, 'security.php_end_of_life', [$cycle['cycle'], date(self::DATE_FORMAT, $securitySupportUntil)])];
        }
        if ($securitySupportUntil < $now + self::PHP_END_OF_LIFE_WARNING_SECONDS) {
            return [new Finding(HealthStatus::Warning, 'security.php_end_of_life_soon', [$cycle['cycle'], date(self::DATE_FORMAT, $securitySupportUntil)])];
        }
        return [];
    }

    private function evaluateHardening(?array $hardening, string $applicationContext): array
    {
        if ($hardening === null) {
            return [new Finding(HealthStatus::Unknown, 'security.no_hardening_data')];
        }
        $findings = [];
        if (($hardening['installToolEnableFileExists'] ?? false) === true) {
            $findings[] = new Finding(HealthStatus::Warning, 'security.install_tool_enabled');
        }
        if ((int)($hardening['displayErrors'] ?? 0) === self::DISPLAY_ERRORS_ENABLED && str_starts_with($applicationContext, 'Production')) {
            $findings[] = new Finding(HealthStatus::Warning, 'security.display_errors');
        }
        if (($hardening['trustedHostsPatternRestricted'] ?? true) === false) {
            $findings[] = new Finding(HealthStatus::Warning, 'security.trusted_hosts');
        }
        $failedLogins = (int)($hardening['failedLoginsLastDay'] ?? 0);
        if ($failedLogins >= self::FAILED_LOGIN_WARNING_THRESHOLD) {
            $findings[] = new Finding(HealthStatus::Warning, 'security.failed_logins', [$failedLogins]);
        }
        return $findings;
    }
}
