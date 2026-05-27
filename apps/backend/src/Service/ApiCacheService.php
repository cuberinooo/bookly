<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ApiCacheService
{
    public function __construct(
        private readonly TagAwareCacheInterface $apiCachePool
    ) {
    }

    /**
     * @param string $entityType e.g., 'course', 'meetup', 'user'
     * @param array $context query params, filters, etc
     * @param callable $loader Function to load data if not in cache
     * @param int $ttl Lifetime in seconds
     */
    public function get(string $entityType, int $companyId, array $context, callable $loader, int $ttl = 300): mixed
    {
        $contextHash = hash('sha256', json_encode($context));
        $key = sprintf('api_cache_%d_%s_%s', $companyId, strtolower($entityType), $contextHash);

        return $this->apiCachePool->get($key, function (ItemInterface $item) use ($entityType, $companyId, $loader, $ttl) {
            $item->expiresAfter($ttl);
            $item->tag([
                sprintf('entity.%s.company.%d', strtolower($entityType), $companyId),
                sprintf('company.%d', $companyId),
            ]);

            return $loader();
        });
    }

    public function invalidateEntity(string $entityType, int $companyId): void
    {
        $this->apiCachePool->invalidateTags([
            sprintf('entity.%s.company.%d', strtolower($entityType), $companyId),
        ]);
    }

    public function invalidateCompany(int $companyId): void
    {
        $this->apiCachePool->invalidateTags([
            sprintf('company.%d', $companyId),
        ]);
    }
}
