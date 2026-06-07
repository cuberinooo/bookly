<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Meetup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MeetupNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private RequestStack $requestStack
    ) {
    }

    public function normalize($data, ?string $format = null, array $context = []): array
    {
        $context['meetup_normalizer_already_called'] = true;
        $normalizedData = $this->normalizer->normalize($data, $format, $context);

        if ($data instanceof Meetup) {
            $imageUrl = $data->getImageUrl();
            if ($imageUrl && !str_starts_with($imageUrl, 'http://') && !str_starts_with($imageUrl, 'https://')) {
                $request = $this->requestStack->getCurrentRequest();
                $baseUrl = $request ? $request->getSchemeAndHttpHost() : '';

                if (empty($baseUrl)) {
                    $baseUrl = $_ENV['DEFAULT_URI'] ?? 'http://localhost';
                }

                $normalizedData['imageUrl'] = sprintf('%s/uploads/%s', rtrim($baseUrl, '/'), ltrim($imageUrl, '/'));
            }
        }

        return $normalizedData;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Meetup && !isset($context['meetup_normalizer_already_called']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Meetup::class => true,
        ];
    }
}
