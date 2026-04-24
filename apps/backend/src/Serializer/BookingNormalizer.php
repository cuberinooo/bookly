<?php

namespace App\Serializer;

use App\Entity\Booking;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class BookingNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private Security $security
    ) {}

    public function normalize($object, string $format = null, array $context = []): array
    {
        // Add a flag to prevent recursion
        $context['booking_normalizer_already_called'] = true;
        $data = $this->normalizer->normalize($object, $format, $context);

        if ($object instanceof Booking) {
            $course = $object->getCourse();
            $trainer = $course->getTrainer();
            $currentUser = $this->security->getUser();

            $settings = $trainer->getSettings();
            $isTrainer = ($currentUser && $currentUser->getUserIdentifier() === $trainer->getUserIdentifier());
            $isOwnBooking = ($currentUser && $currentUser->getUserIdentifier() === $object->getMember()->getUserIdentifier());

            // Check if member is allowed to see this booking if it's waitlist
            if ($object->isWaitlist() && !$settings->isWaitlistVisible() && !$isTrainer && !$isOwnBooking) {
                // If waitlist is not visible to members, we might want to return something empty or handle it in Course normalizer.
                // For now, if we are normalizing a booking that shouldn't be seen, we can mark it as hidden.
                $data['_hidden'] = true;
            }

            $shouldShowName = true;
            if (!$isTrainer && !$isOwnBooking) {
                if ($object->isWaitlist()) {
                    $shouldShowName = $settings->isShowWaitlistNames();
                } else {
                    $shouldShowName = $settings->isShowParticipantNames();
                }
            }

            if (!$shouldShowName) {
                if (isset($data['member']) && is_array($data['member'])) {
                    $data['member']['name'] = 'Athlete #' . $object->getMember()->getId();
                    unset($data['member']['email']);
                }
            }
        }

        return $data;
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof Booking && !isset($context['booking_normalizer_already_called']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Booking::class => true,
        ];
    }
}
