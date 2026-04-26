<?php

namespace App\Serializer;

use App\Entity\Course;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CourseNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private Security $security
    ) {}

    public function normalize($data, ?string $format = null, array $context = []): array
    {
        $context['course_normalizer_already_called'] = true;
        $normalizedData = $this->normalizer->normalize($data, $format, $context);

        if ($data instanceof Course) {
            // Hide trainer email for non-authenticated users
            if (!$this->security->getUser()) {
                if (isset($normalizedData['trainer']) && is_array($normalizedData['trainer'])) {
                    unset($normalizedData['trainer']['email']);
                }
            }

            if (isset($normalizedData['bookings'])) {
                $normalizedData['bookings'] = array_values(array_filter($normalizedData['bookings'], function($booking) {
                    return !isset($booking['_hidden']);
                }));
            }
        }

        return $normalizedData;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
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
