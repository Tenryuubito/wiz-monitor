<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Evaluation;

use Tenryuubito\WizMonitor\Enum\HealthStatus;

final readonly class Finding
{
    public function __construct(
        public HealthStatus $status,
        public string $key,
        public array $arguments = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            HealthStatus::tryFrom((string)($data['status'] ?? '')) ?? HealthStatus::Unknown,
            (string)($data['key'] ?? ''),
            array_values(array_map('strval', is_array($data['arguments'] ?? null) ? $data['arguments'] : [])),
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'key' => $this->key,
            'arguments' => array_values(array_map('strval', $this->arguments)),
        ];
    }
}
