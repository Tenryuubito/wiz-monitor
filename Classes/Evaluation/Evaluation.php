<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Evaluation;

use Tenryuubito\WizMonitor\Enum\HealthCategory;
use Tenryuubito\WizMonitor\Enum\HealthStatus;

final readonly class Evaluation
{
    private function __construct(
        private array $results,
    ) {}

    public static function fromCategoryResults(CategoryResult ...$results): self
    {
        $indexed = [];
        foreach ($results as $result) {
            $indexed[$result->category->value] = $result;
        }
        return new self($indexed);
    }

    public static function fromArray(array $data): self
    {
        $results = [];
        foreach (HealthCategory::cases() as $category) {
            $results[] = is_array($data[$category->value] ?? null)
                ? CategoryResult::fromArray($category, $data[$category->value])
                : new CategoryResult($category, [new Finding(HealthStatus::Unknown, 'general.not_evaluated')]);
        }
        return self::fromCategoryResults(...$results);
    }

    public function getResult(HealthCategory $category): CategoryResult
    {
        return $this->results[$category->value] ?? new CategoryResult($category, [new Finding(HealthStatus::Unknown, 'general.not_evaluated')]);
    }

    public function getResults(): array
    {
        return array_map(fn(HealthCategory $category): CategoryResult => $this->getResult($category), HealthCategory::cases());
    }

    public function getOverallStatus(): HealthStatus
    {
        return HealthStatus::worstOf(...array_map(
            static fn(CategoryResult $result): HealthStatus => $result->status,
            $this->getResults(),
        ));
    }

    public function toArray(): array
    {
        $data = [];
        foreach ($this->getResults() as $result) {
            $data[$result->category->value] = $result->toArray();
        }
        return $data;
    }
}
