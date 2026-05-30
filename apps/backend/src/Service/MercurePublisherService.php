<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CompanyAwareInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

class MercurePublisherService
{
    private readonly string $topicPrefix;
    private array $pendingUpdates = [];

    public function __construct(
        private readonly HubInterface $hub,
        private readonly SerializerInterface $serializer,
        string $topicPrefix = 'https://example.com/api'
    ) {
        $this->topicPrefix = rtrim($topicPrefix, '/');
    }

    public function publishUpdate(string $topic, array $data = []): void
    {
        $update = new Update(
            $topic,
            $this->serializer->serialize($data, 'json')
        );

        $this->hub->publish($update);
    }

    public function publishEntityUpdate(object $entity, string $action, array $extraData = []): void
    {
        $className = (new \ReflectionClass($entity))->getShortName();
        // If it's a proxy or mock, we might need to get the parent class
        if (str_contains($className, 'MockObject') || str_contains($className, 'Proxy')) {
            $reflection = new \ReflectionClass($entity);
            if ($parent = $reflection->getParentClass()) {
                $className = $parent->getShortName();
            }
        }

        $data = [
            'entity' => $className,
            'action' => $action,
            'id' => method_exists($entity, 'getId') ? $entity->getId() : null,
        ];

        $data = array_merge($data, $extraData);

        if ($entity instanceof CompanyAwareInterface && $entity->getCompany()) {
            $data['companyId'] = $entity->getCompany()->getId();
        }

        $this->pendingUpdates[] = $data;

        // Safety: flush if queue exceeds 50 items
        if (count($this->pendingUpdates) >= 50) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->pendingUpdates)) {
            return;
        }

        // Group by companyId (if available) and topic
        $batches = [];
        foreach ($this->pendingUpdates as $update) {
            $companyId = $update['companyId'] ?? 'global';
            $entityType = strtolower($update['entity']);
            $topic = sprintf('%s/%s', $this->topicPrefix, $entityType);

            $key = $companyId.':'.$topic;
            if (!isset($batches[$key])) {
                $batches[$key] = [
                    'topic' => $topic,
                    'updates' => [],
                ];
            }

            // Deduplicate: same entity ID -> latest action wins
            $entityId = (string) ($update['id'] ?? '');
            $batches[$key]['updates'][$entityId] = $update;
        }

        foreach ($batches as $batch) {
            $payload = [
                'batch' => true,
                'updates' => array_values($batch['updates']),
            ];

            // If only one update, keep it simple (optional, but good for compatibility)
            if (1 === count($payload['updates'])) {
                $payload = $payload['updates'][0];
            }

            $this->publishUpdate($batch['topic'], $payload);
        }

        $this->pendingUpdates = [];
    }
}
