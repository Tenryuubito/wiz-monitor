<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Domain\Model;

final readonly class ProbeResult
{
    public function __construct(
        public string $url,
        public bool $reachable,
        public int $httpStatus,
        public int $responseTimeMilliseconds,
        public ?bool $keywordFound,
        public int $tlsExpiresAt,
        public string $failureReason,
    ) {}
}
