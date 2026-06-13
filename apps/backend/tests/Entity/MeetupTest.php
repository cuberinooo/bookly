<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Meetup;
use PHPUnit\Framework\TestCase;

class MeetupTest extends TestCase
{
    public function test_set_image_url_cleans_path(): void
    {
        $meetup = new Meetup();

        // 1. Full absolute URL with http
        $meetup->setImageUrl('http://localhost:8000/uploads/company-slug/meetups/meetup_123.jpg');
        $this->assertSame('company-slug/meetups/meetup_123.jpg', $meetup->getImageUrl());

        // 2. Full absolute URL with https
        $meetup->setImageUrl('https://example.com/uploads/another-slug/meetups/meetup_456.png');
        $this->assertSame('another-slug/meetups/meetup_456.png', $meetup->getImageUrl());

        // 3. Path starting with /uploads/
        $meetup->setImageUrl('/uploads/company-slug/meetups/meetup_789.gif');
        $this->assertSame('company-slug/meetups/meetup_789.gif', $meetup->getImageUrl());

        // 4. Already clean relative path
        $meetup->setImageUrl('company-slug/meetups/meetup_abc.jpg');
        $this->assertSame('company-slug/meetups/meetup_abc.jpg', $meetup->getImageUrl());

        // 5. Null value
        $meetup->setImageUrl(null);
        $this->assertNull($meetup->getImageUrl());
    }
}
