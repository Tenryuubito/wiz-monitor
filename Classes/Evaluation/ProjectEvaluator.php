<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Evaluation;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Tenryuubito\WizMonitor\Enum\HealthCategory;
use Tenryuubito\WizMonitor\Enum\HealthStatus;

final readonly class ProjectEvaluator
{
    public function __construct(
        #[AutowireIterator(CategoryEvaluatorInterface::TAG_NAME)]
        private iterable $evaluators,
    ) {}

    public function evaluate(EvaluationContext $context): Evaluation
    {
        $findingsByCategory = array_fill_keys(array_map(
            static fn(HealthCategory $category): string => $category->value,
            HealthCategory::cases(),
        ), []);

        foreach ($this->evaluators as $evaluator) {
            try {
                $findings = $evaluator->evaluate($context);
            } catch (\Throwable $throwable) {
                $findings = [new Finding(HealthStatus::Unknown, 'general.evaluation_failed', [$throwable->getMessage()])];
            }
            array_push($findingsByCategory[$evaluator->getCategory()->value], ...$findings);
        }

        return Evaluation::fromCategoryResults(...array_map(
            static fn(HealthCategory $category): CategoryResult => new CategoryResult($category, $findingsByCategory[$category->value]),
            HealthCategory::cases(),
        ));
    }
}
