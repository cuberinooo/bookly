<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

class MercurePublisherService
{
    private readonly string $topicPrefix;

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

    public function publishEntityUpdate(object $entity, string $action): void
    {
        $className = (new \ReflectionClass($entity))->getShortName();
        // If it's a proxy or mock, we might need to get the parent class
        if (str_contains($className, 'MockObject') || str_contains($className, 'Proxy')) {
            $reflection = new \ReflectionClass($entity);
            if ($parent = $reflection->getParentClass()) {
                $className = $parent->getShortName();
            }
        }
        
        $topic = sprintf('%s/%s', $this->topicPrefix, strtolower($className));
        
        $this->publishUpdate($topic, [
            'entity' => $className,
            'action' => $action,
            'id' => method_exists($entity, 'getId') ? $entity->getId() : null,
        ]);
    }
}
