<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final readonly class MonitorConfiguration
{
    private const EXTENSION_KEY = 'wiz_monitor';
    private const DEFAULT_LANGUAGE = 'en';
    private const DEFAULT_REQUEST_TIMEOUT = 10;
    private const DEFAULT_SLOW_RESPONSE_THRESHOLD = 3000;
    private const DEFAULT_RETENTION_DAYS = 30;

    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function getNotificationRecipient(): string
    {
        $recipient = trim((string)$this->get('notificationRecipient', ''));
        return filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false ? $recipient : '';
    }

    public function getNotificationLanguage(): string
    {
        return trim((string)$this->get('notificationLanguage', self::DEFAULT_LANGUAGE)) ?: self::DEFAULT_LANGUAGE;
    }

    public function getRequestTimeout(): int
    {
        return max(1, (int)$this->get('requestTimeout', self::DEFAULT_REQUEST_TIMEOUT));
    }

    public function getSlowResponseThreshold(): int
    {
        return max(1, (int)$this->get('slowResponseThreshold', self::DEFAULT_SLOW_RESPONSE_THRESHOLD));
    }

    public function getRetentionDays(): int
    {
        return max(1, (int)$this->get('retentionDays', self::DEFAULT_RETENTION_DAYS));
    }

    private function get(string $option, string|int $default): mixed
    {
        try {
            return $this->extensionConfiguration->get(self::EXTENSION_KEY, $option) ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
