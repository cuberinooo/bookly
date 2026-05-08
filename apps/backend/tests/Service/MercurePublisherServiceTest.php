<?php

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Service\MercurePublisherService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

class MercurePublisherServiceTest extends TestCase
{
    public function testPublishEntityUpdateDispatchesCorrectUpdate(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        
        $topicPrefix = 'http://localhost:8000/api';
        $service = new MercurePublisherService($hub, $serializer, $topicPrefix);

        $booking = $this->createMock(Booking::class);
        $booking->method('getId')->willReturn(123);

        $expectedData = [
            'entity' => 'Booking',
            'action' => 'updated',
            'id' => 123,
        ];

        $serializer->expects($this->once())
            ->method('serialize')
            ->with($expectedData, 'json')
            ->willReturn(json_encode($expectedData));

        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) use ($topicPrefix) {
                return $update->getTopics() === [$topicPrefix . '/booking']
                    && $update->getData() === json_encode([
                        'entity' => 'Booking',
                        'action' => 'updated',
                        'id' => 123,
                    ]);
            }));

        $service->publishEntityUpdate($booking, 'updated');
    }
}
