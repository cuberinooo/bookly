<?php

namespace App\Serializer;

use App\Entity\Booking;
use App\Repository\GlobalSettingsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class BookingNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private Security $security,
        private GlobalSettingsRepository $settingsRepository
    ) {}

    public function normalize($data, ?string $format = null, array $context = []): array
    {
        // Add a flag to prevent recursion
        $context['booking_normalizer_already_called'] = true;
        $normalizedData = $this->normalizer->normalize($data, $format, $context);

        if ($data instanceof Booking) {
            $course = $data->getCourse();
            $trainer = $course->getTrainer();
            $currentUser = $this->security->getUser();

            $settings = $this->settingsRepository->get();
            $isTrainer = ($currentUser && $currentUser->getUserIdentifier() === $trainer->getUserIdentifier());
            $isOwnBooking = ($currentUser && $currentUser->getUserIdentifier() === $data->getMember()->getUserIdentifier());

            // Check if member is allowed to see this booking if it's waitlist
            if ($data->isWaitlist() && !$settings->isWaitlistVisible() && !$isTrainer && !$isOwnBooking) {
                // If waitlist is not visible to members, we might want to return something empty or handle it in Course normalizer.
                // For now, if we are normalizing a booking that shouldn't be seen, we can mark it as hidden.
                $normalizedData['_hidden'] = true;
            }

            $shouldShowName = true;
            if (!$isTrainer && !$isOwnBooking) {
                $shouldShowName = $settings->isShowParticipantNames();
            }

            if (!$shouldShowName) {
                if (isset($normalizedData['member']) && is_array($normalizedData['member'])) {
                    $normalizedData['member']['name'] = 'Athlete #' . $data->getMember()->getId();
                }
            }

            // Always hide email unless it's the trainer or the member's own booking
            if (!$isTrainer && !$isOwnBooking) {
                if (isset($normalizedData['member']) && is_array($normalizedData['member'])) {
                    unset($normalizedData['member']['email']);
                }
            }
        }

        return $normalizedData;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
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
