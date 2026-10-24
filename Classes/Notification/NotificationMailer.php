<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Notification;

use Symfony\Component\Mime\Address;
use Tenryuubito\WizMonitor\Domain\Model\Project;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Evaluation\Finding;
use Tenryuubito\WizMonitor\Incident\IncidentTransition;
use Tenryuubito\WizMonitor\Service\MonitorConfiguration;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\MailUtility;

final readonly class NotificationMailer
{
    public const LANGUAGE_FILE = 'LLL:EXT:wiz_monitor/Resources/Private/Language/locallang_be.xlf';
    private const TEMPLATE_NAME = 'MonitorNotification';
    private const MAXIMUM_LISTED_ERRORS = 10;

    public function __construct(
        private MailerInterface $mailer,
        private MonitorConfiguration $monitorConfiguration,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function send(Project $project, array $transitions, array $newErrors): bool
    {
        $recipient = $this->monitorConfiguration->getNotificationRecipient();
        if ($recipient === '' || ($transitions === [] && $newErrors === [])) {
            return false;
        }

        $language = $this->monitorConfiguration->getNotificationLanguage();
        $languageService = $this->languageServiceFactory->create($language);
        $headlineStatus = HealthStatus::worstOf(...array_map(
            static fn(IncidentTransition $transition): HealthStatus => $transition->status,
            $transitions,
        ));
        if ($transitions === []) {
            $headlineStatus = HealthStatus::Warning;
        }
        $subject = sprintf(
            $languageService->sL(self::LANGUAGE_FILE . ':notification.subject'),
            $this->translateStatus($headlineStatus, $languageService),
            $project->title,
        );

        $email = new FluidEmail();
        $email
            ->to(new Address($recipient))
            ->from(new Address(MailUtility::getSystemFromAddress(), MailUtility::getSystemFromName() ?? ''))
            ->subject($subject)
            ->setTemplate(self::TEMPLATE_NAME)
            ->assignMultiple([
                'headline' => $subject,
                'language' => $language,
                'project' => $project,
                'transitions' => array_map(
                    fn(IncidentTransition $transition): array => $this->mapTransition($transition, $languageService),
                    $transitions,
                ),
                'newErrors' => array_slice($newErrors, 0, self::MAXIMUM_LISTED_ERRORS),
                'newErrorCount' => count($newErrors),
                'hiddenErrorCount' => max(0, count($newErrors) - self::MAXIMUM_LISTED_ERRORS),
            ]);
        $this->mailer->send($email);
        return true;
    }

    private function mapTransition(IncidentTransition $transition, LanguageService $languageService): array
    {
        return [
            'category' => $transition->category->value,
            'type' => $transition->type,
            'previousStatusLabel' => $this->translateStatus($transition->previousStatus, $languageService),
            'statusLabel' => $this->translateStatus($transition->status, $languageService),
            'findings' => array_map(
                fn(Finding $finding): array => $finding->toArray() + ['statusLabel' => $this->translateStatus($finding->status, $languageService)],
                $transition->findings,
            ),
        ];
    }

    private function translateStatus(HealthStatus $status, LanguageService $languageService): string
    {
        return $languageService->sL(self::LANGUAGE_FILE . ':status.' . $status->value);
    }
}
