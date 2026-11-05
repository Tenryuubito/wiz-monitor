<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Security;

use WizTech\ToolkitCore\Security\SecretVault as ToolkitSecretVault;

/**
 * Projektgeheimnisse des Monitors. Nutzt den Tresor aus toolkit_core mit dem bisherigen Schlüsselkontext,
 * damit vorhandene Geheimnisse nach der Übernahme in das Toolkit gültig bleiben.
 */
final readonly class SecretVault
{
    private const KEY_CONTEXT = 'tenryuubito/wiz-monitor:project-secret';

    public function __construct(private ToolkitSecretVault $vault) {}

    public function generateSecret(): string
    {
        return $this->vault->generateSecret();
    }

    public function encrypt(string $secret): string
    {
        return $this->vault->encrypt($secret, self::KEY_CONTEXT);
    }

    public function decrypt(string $encryptedSecret): ?string
    {
        return $this->vault->decrypt($encryptedSecret, self::KEY_CONTEXT);
    }
}
