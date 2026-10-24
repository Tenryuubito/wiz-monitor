<?php

declare(strict_types=1);

namespace Tenryuubito\WizMonitor\External;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Tenryuubito\WizMonitor\Service\MonitorConfiguration;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

final readonly class ExternalDataClient
{
    public const CACHE_IDENTIFIER = 'wiz_monitor_external';
    private const USER_AGENT = 'tenryuubito/wiz-monitor';

    public function __construct(
        private RequestFactory $requestFactory,
        private MonitorConfiguration $monitorConfiguration,
        #[Autowire(service: 'cache.wiz_monitor_external')]
        private FrontendInterface $cache,
    ) {}

    public function getJson(string $url, int $lifetime): ?array
    {
        return $this->fetchCached('get_' . sha1($url), $lifetime, fn(): array => $this->request('GET', $url, []));
    }

    public function postFormJson(string $url, array $formParameters, int $lifetime): ?array
    {
        $cacheIdentifier = 'post_' . sha1($url . "\n" . http_build_query($formParameters));
        return $this->fetchCached($cacheIdentifier, $lifetime, fn(): array => $this->request('POST', $url, ['form_params' => $formParameters]));
    }

    private function fetchCached(string $cacheIdentifier, int $lifetime, \Closure $fetch): ?array
    {
        $cached = $this->cache->get($cacheIdentifier);
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $data = $fetch();
        } catch (\Throwable) {
            return null;
        }
        $this->cache->set($cacheIdentifier, $data, [], $lifetime);
        return $data;
    }

    private function request(string $method, string $url, array $options): array
    {
        $timeout = $this->monitorConfiguration->getRequestTimeout();
        $response = $this->requestFactory->request($url, $method, $options + [
            'timeout' => $timeout,
            'connect_timeout' => $timeout,
            'headers' => ['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Unexpected status ' . $response->getStatusCode() . ' from ' . $url, 1761300002);
        }
        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Unexpected payload from ' . $url, 1761300003);
        }
        return $data;
    }
}
