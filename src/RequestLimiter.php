<?php declare(strict_types=1);
namespace Domm98CZ\RequestLimiter;

use Nette\Caching\Cache;
use Nette\Caching\Storages\FileStorage;

class RequestLimiter
{
    private Cache $cache;

    public function __construct(
        string $tmpDirPath,
        private readonly string $host,
        private int $maxRequests = -1,
        private readonly int $secondsInterval = 60,
        readonly int $safeLimit = -1
    ) {
        $storage = new FileStorage($tmpDirPath);
        $this->cache = new Cache($storage, '_requestLimiter');

        if ($safeLimit > 0) {
            $this->maxRequests = (int) ceil($maxRequests * (1 - $safeLimit / 100));
        }
    }

    /**
     * @return string
     */
    private static function getCacheKey(string $host): string
    {
        return sprintf('requests-%s', md5($host));
    }

    /**
     * @return void
     */
    public function acquire(): void {
        while (true) {
            $now = microtime(true);
            $requests = $this->cache->load(self::getCacheKey($this->host)) ?? [];
            $requests = array_filter($requests, fn($timestamp) => $timestamp > ($now - $this->secondsInterval));

            if (count($requests) < $this->maxRequests) {
                $requests[] = $now;
                $this->cache->save(self::getCacheKey($this->host), $requests, [
                    Cache::Expire => $this->secondsInterval . ' seconds',
                ]);
                return;
            }

            sleep(5);
        }
    }
}
