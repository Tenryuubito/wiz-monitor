<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Check;

use Tenryuubito\WizMonitor\Domain\Model\ProbeResult;
use Tenryuubito\WizMonitor\Service\MonitorConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;

final readonly class HttpProbe
{
    private const USER_AGENT = 'tenryuubito/wiz-monitor uptime check';
    private const MAXIMUM_REDIRECTS = 5;

    public function __construct(
        private RequestFactory $requestFactory,
        private TlsCertificateInspector $tlsCertificateInspector,
        private MonitorConfiguration $monitorConfiguration,
    ) {}

    public function probe(string $url, string $expectedKeyword): ProbeResult
    {
        $timeout = $this->monitorConfiguration->getRequestTimeout();
        $tlsExpiresAt = $this->tlsCertificateInspector->findExpiryTimestamp($url);
        $startedAt = hrtime(true);

        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
                'http_errors' => false,
                'allow_redirects' => ['max' => self::MAXIMUM_REDIRECTS],
                'headers' => ['User-Agent' => self::USER_AGENT, 'Cache-Control' => 'no-cache'],
            ]);
            $body = (string)$response->getBody();
        } catch (\Throwable $throwable) {
            return new ProbeResult(
                url: $url,
                reachable: false,
                httpStatus: 0,
                responseTimeMilliseconds: $this->elapsedMilliseconds($startedAt),
                keywordFound: null,
                tlsExpiresAt: $tlsExpiresAt,
                failureReason: $throwable->getMessage(),
            );
        }

        $statusCode = $response->getStatusCode();
        return new ProbeResult(
            url: $url,
            reachable: $statusCode >= 200 && $statusCode < 400,
            httpStatus: $statusCode,
            responseTimeMilliseconds: $this->elapsedMilliseconds($startedAt),
            keywordFound: $expectedKeyword === '' ? null : str_contains($body, $expectedKeyword),
            tlsExpiresAt: $tlsExpiresAt,
            failureReason: '',
        );
    }

    private function elapsedMilliseconds(int|float $startedAt): int
    {
        return (int)round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
