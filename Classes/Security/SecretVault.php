<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Security;

final readonly class SecretVault
{
    private const SECRET_BYTES = 32;
    private const KEY_CONTEXT = 'tenryuubito/wiz-monitor:project-secret';

    public function generateSecret(): string
    {
        return sodium_bin2base64(random_bytes(self::SECRET_BYTES), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    public function encrypt(string $secret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return sodium_bin2base64($nonce . sodium_crypto_secretbox($secret, $nonce, $this->deriveKey()), SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    public function decrypt(string $encryptedSecret): ?string
    {
        if ($encryptedSecret === '') {
            return null;
        }
        try {
            $decoded = sodium_base642bin($encryptedSecret, SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (\SodiumException) {
            return null;
        }
        if (strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $secret = sodium_crypto_secretbox_open(
            substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->deriveKey(),
        );
        return $secret === false ? null : $secret;
    }

    private function deriveKey(): string
    {
        $encryptionKey = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');
        if ($encryptionKey === '') {
            throw new \RuntimeException('TYPO3 encryptionKey is not configured', 1761300001);
        }
        return hash_hkdf('sha256', $encryptionKey, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, self::KEY_CONTEXT);
    }
}
