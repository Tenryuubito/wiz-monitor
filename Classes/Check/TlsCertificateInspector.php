<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Check;

use Tenryuubito\WizMonitor\Service\MonitorConfiguration;

final readonly class TlsCertificateInspector
{
    private const DEFAULT_HTTPS_PORT = 443;

    public function __construct(
        private MonitorConfiguration $monitorConfiguration,
    ) {}

    public function findExpiryTimestamp(string $url): int
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])) {
            return 0;
        }
        $host = $parts['host'];
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'SNI_enabled' => true,
                'peer_name' => $host,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        try {
            $client = stream_socket_client(
                'ssl://' . $host . ':' . ($parts['port'] ?? self::DEFAULT_HTTPS_PORT),
                $errorCode,
                $errorMessage,
                $this->monitorConfiguration->getRequestTimeout(),
                STREAM_CLIENT_CONNECT,
                $context,
            );
        } catch (\Throwable) {
            return 0;
        }
        if ($client === false) {
            return 0;
        }
        $certificate = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        fclose($client);
        if ($certificate === null) {
            return 0;
        }
        $parsedCertificate = openssl_x509_parse($certificate);
        return is_array($parsedCertificate) ? (int)($parsedCertificate['validTo_time_t'] ?? 0) : 0;
    }
}
