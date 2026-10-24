<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Domain\Model;

final readonly class Snapshot
{
    public function __construct(
        public int $projectUid,
        public int $lastAttemptAt,
        public int $lastSuccessAt,
        public string $failureReason,
        public array $payload,
        public int $errorCursor,
        public array $evaluation,
        public int $evaluatedAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            projectUid: (int)$row['project'],
            lastAttemptAt: (int)$row['last_attempt_at'],
            lastSuccessAt: (int)$row['last_success_at'],
            failureReason: (string)$row['failure_reason'],
            payload: self::decodeJson((string)($row['payload'] ?? '')),
            errorCursor: (int)$row['error_cursor'],
            evaluation: self::decodeJson((string)($row['evaluation'] ?? '')),
            evaluatedAt: (int)$row['evaluated_at'],
        );
    }

    public function getCollectorData(string $collector): ?array
    {
        $section = $this->payload['collectors'][$collector] ?? null;
        if (!is_array($section) || ($section['state'] ?? '') !== 'collected' || !is_array($section['data'] ?? null)) {
            return null;
        }
        return $section['data'];
    }

    public function getFailedCollectors(): array
    {
        $failed = [];
        foreach ($this->payload['collectors'] ?? [] as $identifier => $section) {
            if (is_array($section) && ($section['state'] ?? '') === 'failed') {
                $failed[(string)$identifier] = (string)($section['message'] ?? '');
            }
        }
        return $failed;
    }

    private static function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        return is_array($data) ? $data : [];
    }
}
