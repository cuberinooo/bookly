<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Entity\Meetup;
use App\Serializer\MeetupNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MeetupNormalizerTest extends TestCase
{
    private $objectNormalizer;
    private $requestStack;
    private $normalizer;

    protected function setUp(): void
    {
        $this->objectNormalizer = $this->createMock(NormalizerInterface::class);
        $this->requestStack = new RequestStack();
        $this->normalizer = new MeetupNormalizer($this->objectNormalizer, $this->requestStack);
    }

    public function test_normalize_relative_image_url_with_request(): void
    {
        $meetup = new Meetup();
        $meetup->setImageUrl('company-slug/meetups/meetup_123.jpg');

        $request = Request::create('http://myhost.local/api/meetups');
        $this->requestStack->push($request);

        $this->objectNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn(['imageUrl' => 'company-slug/meetups/meetup_123.jpg']);

        $result = $this->normalizer->normalize($meetup);

        $this->assertSame('http://myhost.local/uploads/company-slug/meetups/meetup_123.jpg', $result['imageUrl']);
    }

    public function test_normalize_relative_image_url_without_request_fallback(): void
    {
        $meetup = new Meetup();
        $meetup->setImageUrl('company-slug/meetups/meetup_123.jpg');

        $_ENV['DEFAULT_URI'] = 'https://fallback.local';

        $this->objectNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn(['imageUrl' => 'company-slug/meetups/meetup_123.jpg']);

        $result = $this->normalizer->normalize($meetup);

        $this->assertSame('https://fallback.local/uploads/company-slug/meetups/meetup_123.jpg', $result['imageUrl']);

        unset($_ENV['DEFAULT_URI']);
    }

    public function test_normalize_absolute_image_url_unchanged(): void
    {
        $meetup = new Meetup();
        $meetup->setImageUrl('http://external-domain.com/placeholder.jpg');

        $this->objectNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn(['imageUrl' => 'http://external-domain.com/placeholder.jpg']);

        $result = $this->normalizer->normalize($meetup);

        $this->assertSame('http://external-domain.com/placeholder.jpg', $result['imageUrl']);
    }
}
