<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Domain\Model;

final readonly class FetchResult
{
    private function __construct(
        public bool $succeeded,
        public array $payload,
        public array $errorItems,
        public int $errorCursor,
        public string $failureReason,
    ) {}

    public static function success(array $payload, array $errorItems, int $errorCursor): self
    {
        return new self(true, $payload, $errorItems, $errorCursor, '');
    }

    public static function failure(string $failureReason): self
    {
        return new self(false, [], [], 0, $failureReason);
    }
}
