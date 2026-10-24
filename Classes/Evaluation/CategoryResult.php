<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Evaluation;

use Tenryuubito\WizMonitor\Enum\HealthCategory;
use Tenryuubito\WizMonitor\Enum\HealthStatus;

final readonly class CategoryResult
{
    public HealthStatus $status;

    public function __construct(
        public HealthCategory $category,
        public array $findings,
    ) {
        $this->status = HealthStatus::worstOf(...array_map(
            static fn(Finding $finding): HealthStatus => $finding->status,
            $findings,
        ));
    }

    public static function fromArray(HealthCategory $category, array $data): self
    {
        return new self($category, array_map(
            static fn(mixed $finding): Finding => Finding::fromArray(is_array($finding) ? $finding : []),
            is_array($data['findings'] ?? null) ? $data['findings'] : [],
        ));
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'findings' => array_map(static fn(Finding $finding): array => $finding->toArray(), $this->findings),
        ];
    }
}
