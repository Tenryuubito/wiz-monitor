<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\Check;

use Tenryuubito\WizMonitor\Domain\Model\FetchResult;
use Tenryuubito\WizMonitor\Domain\Model\Project;
use Tenryuubito\WizMonitor\Security\SecretVault;
use Tenryuubito\WizMonitor\Service\MonitorConfiguration;
use Tenryuubito\WizMonitorClient\Middleware\StatusEndpointMiddleware;
use Tenryuubito\WizMonitorClient\Security\RequestSigner;
use Tenryuubito\WizMonitorClient\Status\StatusReport;
use Tenryuubito\WizMonitorClient\Status\StatusRequest;
use TYPO3\CMS\Core\Http\RequestFactory;

final readonly class StatusFetcher
{
    private const MAXIMUM_ERROR_PAGES = 5;
    private const USER_AGENT = 'tenryuubito/wiz-monitor';

    public function __construct(
        private RequestFactory $requestFactory,
        private RequestSigner $requestSigner,
        private SecretVault $secretVault,
        private MonitorConfiguration $monitorConfiguration,
    ) {}

    public function fetch(Project $project, int $errorCursor): FetchResult
    {
        $secret = $this->secretVault->decrypt($project->encryptedSecret);
        if ($secret === null) {
            return FetchResult::failure('No valid secret configured for this project');
        }

        $endpoint = $project->baseUrl . '/' . StatusEndpointMiddleware::ENDPOINT_PATH;
        $errorItems = [];
        $payload = [];
        $cursor = $errorCursor;

        for ($page = 0; $page < self::MAXIMUM_ERROR_PAGES; $page++) {
            try {
                $payload = $this->requestPage($endpoint, $secret, new StatusRequest($cursor));
            } catch (\Throwable $throwable) {
                return FetchResult::failure($throwable->getMessage());
            }
            $errors = $payload['collectors']['errors']['data'] ?? [];
            foreach (is_array($errors['items'] ?? null) ? $errors['items'] : [] as $item) {
                if (is_array($item) && is_string($item['fingerprint'] ?? null)) {
                    $errorItems[$item['fingerprint']] = $item;
                }
            }
            $nextCursor = max($cursor, (int)($errors['cursor'] ?? $cursor));
            $hasMore = ($errors['hasMore'] ?? false) === true && $nextCursor > $cursor;
            $cursor = $nextCursor;
            if (!$hasMore) {
                break;
            }
        }

        unset($payload['collectors']['errors']['data']['items']);
        return FetchResult::success($payload, array_values($errorItems), $cursor);
    }

    private function requestPage(string $endpoint, string $secret, StatusRequest $statusRequest): array
    {
        $timestamp = time();
        $nonce = $this->requestSigner->createNonce();
        $body = $statusRequest->toJson();
        $path = (string)parse_url($endpoint, PHP_URL_PATH);
        $timeout = $this->monitorConfiguration->getRequestTimeout();

        $response = $this->requestFactory->request($endpoint, 'POST', [
            'timeout' => $timeout,
            'connect_timeout' => $timeout,
            'http_errors' => false,
            'allow_redirects' => false,
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => self::USER_AGENT,
                RequestSigner::HEADER_TIMESTAMP => (string)$timestamp,
                RequestSigner::HEADER_NONCE => $nonce,
                RequestSigner::HEADER_SIGNATURE => $this->requestSigner->signRequest($secret, 'POST', $path, $timestamp, $nonce, $body),
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Status endpoint responded with HTTP ' . $response->getStatusCode(), 1761300004);
        }

        $responseBody = (string)$response->getBody();
        $responseTimestamp = $response->getHeaderLine(RequestSigner::HEADER_TIMESTAMP);
        $expectedSignature = $this->requestSigner->signResponse($secret, $nonce, (int)$responseTimestamp, $responseBody);
        if (
            !ctype_digit($responseTimestamp)
            || !$this->requestSigner->isTimestampWithinTolerance((int)$responseTimestamp, time())
            || !$this->requestSigner->matches($expectedSignature, $response->getHeaderLine(RequestSigner::HEADER_SIGNATURE))
        ) {
            throw new \RuntimeException('Status response signature is invalid', 1761300005);
        }

        $payload = json_decode($responseBody, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || ($payload['schema'] ?? null) !== StatusReport::SCHEMA_VERSION) {
            throw new \RuntimeException('Status response uses an unsupported schema', 1761300006);
        }
        return $payload;
    }
}
