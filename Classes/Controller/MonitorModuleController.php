<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tenryuubito\WizMonitor\Domain\Model\Project;
use Tenryuubito\WizMonitor\Domain\Model\Snapshot;
use Tenryuubito\WizMonitor\Domain\Repository\CheckRepository;
use Tenryuubito\WizMonitor\Domain\Repository\ErrorRepository;
use Tenryuubito\WizMonitor\Domain\Repository\IncidentRepository;
use Tenryuubito\WizMonitor\Domain\Repository\ProjectRepository;
use Tenryuubito\WizMonitor\Domain\Repository\SnapshotRepository;
use Tenryuubito\WizMonitor\Enum\HealthStatus;
use Tenryuubito\WizMonitor\Evaluation\CategoryResult;
use Tenryuubito\WizMonitor\Evaluation\Evaluation;
use Tenryuubito\WizMonitor\Evaluation\Finding;
use Tenryuubito\WizMonitor\Notification\NotificationMailer;
use Tenryuubito\WizMonitor\Security\SecretVault;
use Tenryuubito\WizMonitor\Service\MonitorRunner;
use Tenryuubito\WizMonitorClient\Security\SecretProvider;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
final readonly class MonitorModuleController
{
    private const MODULE_IDENTIFIER = 'wiz_monitor';
    private const LATEST_ERROR_LIMIT = 50;
    private const LATEST_INCIDENT_LIMIT = 20;
    private const ADVISORY_FINDING_KEY = 'security.advisory';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private ComponentFactory $componentFactory,
        private IconFactory $iconFactory,
        private ProjectRepository $projectRepository,
        private SnapshotRepository $snapshotRepository,
        private CheckRepository $checkRepository,
        private ErrorRepository $errorRepository,
        private IncidentRepository $incidentRepository,
        private SecretVault $secretVault,
        private MonitorRunner $monitorRunner,
    ) {}

    public function overviewAction(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->createView($request, 'module.overview.title');
        $this->addNewProjectButton($view, $request);

        $snapshots = $this->snapshotRepository->findAllIndexedByProject();
        $rows = array_map(
            fn(Project $project): array => $this->buildOverviewRow($project, $snapshots[$project->uid] ?? null),
            $this->projectRepository->findAll(),
        );

        return $view->assignMultiple([
            'rows' => $rows,
            'categories' => array_map(static fn(CategoryResult $result): string => $result->category->value, Evaluation::fromArray([])->getResults()),
        ])->renderResponse('MonitorModule/Overview');
    }

    public function detailAction(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);
        if ($project === null) {
            return $this->redirectToOverview();
        }

        $view = $this->createView($request, 'module.detail.title');
        $view->addButtonToButtonBar(
            $this->componentFactory->createBackButton((string)$this->uriBuilder->buildUriFromRoute(self::MODULE_IDENTIFIER)),
            ButtonBar::BUTTON_POSITION_LEFT,
            1,
        );
        $view->addButtonToButtonBar(
            $this->componentFactory->createLinkButton()
                ->setHref($this->buildEditUri($project->uid, $request))
                ->setTitle($this->translate('module.action.edit'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-open', IconSize::SMALL)),
            ButtonBar::BUTTON_POSITION_LEFT,
            2,
        );

        $snapshot = $this->snapshotRepository->findByProject($project->uid);
        $evaluation = Evaluation::fromArray($snapshot?->evaluation ?? []);
        $packages = $snapshot?->getCollectorData('packages')['packages'] ?? [];

        return $view->assignMultiple([
            'project' => $project,
            'snapshot' => $snapshot,
            'overallStatus' => $snapshot !== null && $snapshot->evaluatedAt > 0 ? $evaluation->getOverallStatus()->value : HealthStatus::Unknown->value,
            'categoryResults' => array_map($this->mapCategoryResult(...), $evaluation->getResults()),
            'checks' => array_map(
                static fn(array $checks): array => $checks[0],
                $this->checkRepository->findRecentGroupedByUrl($project->uid, $project->checkUrls, 1),
            ),
            'errors' => $this->errorRepository->findLatest($project->uid, self::LATEST_ERROR_LIMIT),
            'incidents' => array_map($this->mapIncident(...), $this->incidentRepository->findLatest($project->uid, self::LATEST_INCIDENT_LIMIT)),
            'environment' => $snapshot?->getCollectorData('environment'),
            'hardening' => $snapshot?->getCollectorData('hardening'),
            'storage' => $snapshot?->getCollectorData('storage'),
            'scheduler' => $snapshot?->getCollectorData('scheduler'),
            'packages' => $this->markAffectedPackages(is_array($packages) ? $packages : [], $evaluation),
            'secretEnvironmentVariable' => SecretProvider::ENVIRONMENT_VARIABLE,
            'runUri' => (string)$this->uriBuilder->buildUriFromRoute(self::MODULE_IDENTIFIER . '.run'),
            'generateSecretUri' => (string)$this->uriBuilder->buildUriFromRoute(self::MODULE_IDENTIFIER . '.generateSecret'),
        ])->renderResponse('MonitorModule/Detail');
    }

    public function runAction(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);
        if ($project === null) {
            return $this->redirectToOverview();
        }
        $evaluation = $this->monitorRunner->runProject($project);
        $view = $this->moduleTemplateFactory->create($request);
        $evaluation === null
            ? $view->addFlashMessage($this->translate('module.run.failed'), '', ContextualFeedbackSeverity::ERROR)
            : $view->addFlashMessage($this->translate('module.run.completed'), '', ContextualFeedbackSeverity::OK);
        return $this->redirectToDetail($project->uid);
    }

    public function generateSecretAction(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);
        if ($project === null) {
            return $this->redirectToOverview();
        }
        $secret = $this->secretVault->generateSecret();
        $this->projectRepository->updateEncryptedSecret($project->uid, $this->secretVault->encrypt($secret));
        $this->snapshotRepository->removeByProject($project->uid);

        $view = $this->createView($request, 'module.secret.title');
        $view->addButtonToButtonBar(
            $this->componentFactory->createBackButton((string)$this->uriBuilder->buildUriFromRoute(self::MODULE_IDENTIFIER . '.detail', ['project' => $project->uid])),
            ButtonBar::BUTTON_POSITION_LEFT,
            1,
        );
        return $view->assignMultiple([
            'project' => $project,
            'environmentLine' => SecretProvider::ENVIRONMENT_VARIABLE . '=' . $secret,
        ])->renderResponse('MonitorModule/Secret');
    }

    private function createView(ServerRequestInterface $request, string $titleKey): ModuleTemplate
    {
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->translate('module.title'), $this->translate($titleKey));
        return $view;
    }

    private function addNewProjectButton(ModuleTemplate $view, ServerRequestInterface $request): void
    {
        $view->addButtonToButtonBar(
            $this->componentFactory->createLinkButton()
                ->setHref((string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                    'edit' => [ProjectRepository::TABLE_NAME => [0 => 'new']],
                    'returnUrl' => (string)$request->getUri(),
                ]))
                ->setTitle($this->translate('module.action.new'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-plus', IconSize::SMALL)),
            ButtonBar::BUTTON_POSITION_LEFT,
            1,
        );
    }

    private function buildOverviewRow(Project $project, ?Snapshot $snapshot): array
    {
        $isEvaluated = $snapshot !== null && $snapshot->evaluatedAt > 0;
        $evaluation = Evaluation::fromArray($snapshot?->evaluation ?? []);
        return [
            'project' => $project,
            'snapshot' => $snapshot,
            'overallStatus' => $isEvaluated ? $evaluation->getOverallStatus()->value : HealthStatus::Unknown->value,
            'categoryStatuses' => array_map(
                static fn(CategoryResult $result): array => ['category' => $result->category->value, 'status' => $result->status->value],
                $evaluation->getResults(),
            ),
            'detailUri' => (string)$this->uriBuilder->buildUriFromRoute(self::MODULE_IDENTIFIER . '.detail', ['project' => $project->uid]),
        ];
    }

    private function mapCategoryResult(CategoryResult $result): array
    {
        return [
            'category' => $result->category->value,
            'status' => $result->status->value,
            'findings' => array_map(static fn(Finding $finding): array => $finding->toArray(), $result->findings),
        ];
    }

    private function mapIncident(array $incident): array
    {
        $findings = json_decode((string)$incident['findings'], true);
        return $incident + ['decodedFindings' => is_array($findings) ? $findings : []];
    }

    private function markAffectedPackages(array $packages, Evaluation $evaluation): array
    {
        $affectedPackages = [];
        foreach ($evaluation->getResults() as $result) {
            foreach ($result->findings as $finding) {
                if ($finding->key === self::ADVISORY_FINDING_KEY) {
                    $affectedPackages[(string)($finding->arguments[0] ?? '')][] = $finding->arguments;
                }
            }
        }
        $marked = [];
        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }
            $name = (string)($package['name'] ?? '');
            $marked[] = $package + ['advisories' => $affectedPackages[$name] ?? []];
        }
        usort($marked, static fn(array $first, array $second): int => [count($second['advisories']), $first['name']] <=> [count($first['advisories']), $second['name']]);
        return $marked;
    }

    private function resolveProject(ServerRequestInterface $request): ?Project
    {
        $projectUid = (int)($request->getParsedBody()['project'] ?? $request->getQueryParams()['project'] ?? 0);
        return $projectUid > 0 ? $this->projectRepository->findByUid($projectUid) : null;
    }

    private function buildEditUri(int $projectUid, ServerRequestInterface $request): string
    {
        return (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [ProjectRepository::TABLE_NAME => [$projectUid => 'edit']],
            'returnUrl' => (string)$request->getUri(),
        ]);
    }

    private function redirectToOverview(): ResponseInterface
    {
        return new RedirectResponse((string)$this->uriBuilder->buildUriFromRoute(self::MODULE_IDENTIFIER));
    }

    private function redirectToDetail(int $projectUid): ResponseInterface
    {
        return new RedirectResponse((string)$this->uriBuilder->buildUriFromRoute(self::MODULE_IDENTIFIER . '.detail', ['project' => $projectUid]));
    }

    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL(NotificationMailer::LANGUAGE_FILE . ':' . $key);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
