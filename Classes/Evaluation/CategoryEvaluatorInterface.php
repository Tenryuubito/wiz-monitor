<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Evaluation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Tenryuubito\WizMonitor\Enum\HealthCategory;

#[AutoconfigureTag(self::TAG_NAME)]
interface CategoryEvaluatorInterface
{
    public const TAG_NAME = 'wiz_monitor.category_evaluator';

    public function getCategory(): HealthCategory;

    public function evaluate(EvaluationContext $context): array;
}
