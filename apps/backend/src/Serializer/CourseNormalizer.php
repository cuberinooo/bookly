<?php

namespace App\Serializer;

use App\Entity\Course;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CourseNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer
    ) {}

    public function normalize($object, string $format = null, array $context = []): array
    {
        $context['course_normalizer_already_called'] = true;
        $data = $this->normalizer->normalize($object, $format, $context);

        if ($object instanceof Course && isset($data['bookings'])) {
            $data['bookings'] = array_values(array_filter($data['bookings'], function($booking) {
                return !isset($booking['_hidden']);
            }));
        }

        return $data;
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof Course && !isset($context['course_normalizer_already_called']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Course::class => true,
        ];
    }
}
