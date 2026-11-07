<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Toolkit;

use Psr\Http\Message\ServerRequestInterface;
use Tenryuubito\WizMonitor\Domain\Repository\ProjectRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use WizTech\ToolkitCrm\Domain\Project;
use WizTech\ToolkitCrm\Panel\PanelRenderer;
use WizTech\ToolkitCrm\Wizard\ProjectSetupProviderInterface;
use WizTech\ToolkitCrm\Wizard\SetupItem;
use WizTech\ToolkitCrm\Wizard\SetupState;
use WizTech\ToolkitCrm\Wizard\WizardInput;
use WizTech\ToolkitCrm\Wizard\WizardSection;
use WizTech\ToolkitCrm\Wizard\WizardStep;

/**
 * Monitoring im Projekt-Assistenten und in der Einrichtungskarte.
 */
final readonly class MonitorProjectSetup implements ProjectSetupProviderInterface
{
    public function __construct(
        private ToolkitProjectSync $sync,
        private ProjectRepository $projectRepository,
        private PanelRenderer $panelRenderer,
        private UriBuilder $uriBuilder,
    ) {}

    public function getPriority(): int
    {
        return 30;
    }

    public function getDefaults(): array
    {
        return ['monitor_enabled' => '1'];
    }

    public function getWizardSection(WizardInput $input, ServerRequestInterface $request): ?WizardSection
    {
        return new WizardSection(WizardStep::Website, $this->panelRenderer->render('wiz_monitor', 'WizardMonitor', ['input' => $input], $request)->html, 30);
    }

    public function validate(WizardInput $input): array
    {
        $errors = [];
        if ($input->isOn('monitor_enabled') && !$input->hasBaseUrl()) {
            $errors['monitor_enabled'] = 'Für die Überwachung braucht das Projekt eine Basis-URL.';
        }
        if ($input->getString('monitor_check_pages') !== '' && preg_match('/^\d+(\s*,\s*\d+)*$/', $input->getString('monitor_check_pages')) !== 1) {
            $errors['monitor_check_pages'] = 'Bitte Seiten-UIDs durch Komma getrennt angeben, z. B. 1, 12, 34.';
        }
        return $errors;
    }

    public function getProjectFields(WizardInput $input): array
    {
        $enabled = $input->isOn('monitor_enabled') && $input->hasBaseUrl();
        return [
            'monitor_enabled' => $enabled ? 1 : 0,
            'monitor_check_pages' => $enabled ? (string)preg_replace('/\s+/', '', $input->getString('monitor_check_pages')) : '',
            'monitor_expected_keyword' => $enabled ? $input->getString('monitor_expected_keyword') : '',
        ];
    }

    public function getSetupItems(Project $project, ServerRequestInterface $request): array
    {
        if (!(bool)($project->row['monitor_enabled'] ?? false)) {
            return [new SetupItem('Monitoring', SetupState::Off, 'Die Website wird nicht überwacht.', priority: 30)];
        }
        if ($project->baseUrl === '') {
            return [new SetupItem('Monitoring', SetupState::Open, 'Eingeschaltet, aber ohne Basis-URL kann nichts geprüft werden.', priority: 30)];
        }
        $monitorUid = $this->sync->findMonitorUid($project->uid);
        $monitor = $monitorUid > 0 ? $this->projectRepository->findByUid($monitorUid) : null;
        if ($monitor === null) {
            return [new SetupItem('Monitoring', SetupState::Open, 'Der Monitor-Datensatz fehlt. Speichere das Projekt einmal, um ihn anzulegen.', priority: 30)];
        }
        if (!$monitor->isSecretConfigured()) {
            return [new SetupItem(
                'Monitoring',
                SetupState::Open,
                'Es fehlt das Client-Secret für wiz-monitor-client. Ohne prüft der Monitor nur die Erreichbarkeit.',
                (string)$this->uriBuilder->buildUriFromRoute('wiz_monitor.generateSecret'),
                'Secret erzeugen',
                ['project' => $monitor->uid],
                30,
            )];
        }
        return [new SetupItem(
            'Monitoring',
            SetupState::Done,
            'Prüft alle fünf Minuten Erreichbarkeit, Sicherheit, Fehler und System.',
            (string)$this->uriBuilder->buildUriFromRoute('wiz_monitor.detail', ['project' => $monitor->uid]),
            'Details',
            priority: 30,
        )];
    }
}
