<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Enum;

enum HealthStatus: string
{
    case Ok = 'ok';
    case Unknown = 'unknown';
    case Warning = 'warning';
    case Critical = 'critical';

    public function getWeight(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Unknown => 1,
            self::Warning => 2,
            self::Critical => 3,
        };
    }

    public function isProblem(): bool
    {
        return $this === self::Warning || $this === self::Critical;
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::Ok => 'badge-success',
            self::Unknown => 'badge-secondary',
            self::Warning => 'badge-warning',
            self::Critical => 'badge-danger',
        };
    }

    public static function worstOf(HealthStatus ...$statuses): self
    {
        $worst = self::Ok;
        foreach ($statuses as $status) {
            if ($status->getWeight() > $worst->getWeight()) {
                $worst = $status;
            }
        }
        return $worst;
    }
}
