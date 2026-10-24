<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tenryuubito\WizMonitor\Domain\Repository\ProjectRepository;
use Tenryuubito\WizMonitor\Enum\HealthCategory;
use Tenryuubito\WizMonitor\Evaluation\Evaluation;
use Tenryuubito\WizMonitor\Service\MonitorRunner;

#[AsCommand(
    name: 'wizmonitor:run',
    description: 'Checks availability, security, errors and system health of all monitored projects.',
)]
final class RunMonitorCommand extends Command
{
    public function __construct(
        private readonly MonitorRunner $monitorRunner,
        private readonly ProjectRepository $projectRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Only check the project with this uid');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectUid = $input->getOption('project');

        if ($projectUid !== null) {
            $project = $this->projectRepository->findByUid((int)$projectUid);
            if ($project === null) {
                $io->error('Project ' . $projectUid . ' does not exist.');
                return Command::INVALID;
            }
            $evaluations = [$project->uid => $this->monitorRunner->runProject($project)];
        } else {
            $evaluations = $this->monitorRunner->runAll();
        }

        $rows = [];
        foreach ($evaluations as $uid => $evaluation) {
            $rows[] = [$uid, ...$this->describeEvaluation($evaluation)];
        }
        $io->table(['uid', ...array_map(static fn(HealthCategory $category): string => $category->value, HealthCategory::cases())], $rows);

        return in_array(null, $evaluations, true) ? Command::FAILURE : Command::SUCCESS;
    }

    private function describeEvaluation(?Evaluation $evaluation): array
    {
        return array_map(
            static fn(HealthCategory $category): string => $evaluation?->getResult($category)->status->value ?? 'failed',
            HealthCategory::cases(),
        );
    }
}
