<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Meetup;
use App\Entity\MeetupRsvp;
use App\Entity\User;
use App\Enum\MeetupStatus;
use App\Enum\RsvpStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class MeetupService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailService $emailService,
        private TranslatorInterface $translator,
        private PushNotificationService $pushService
    ) {
    }

    public function createMeetup(array $data, User $creator): Meetup
    {
        $meetupDate = isset($data['meetupDate']) ? new \DateTime($data['meetupDate']) : null;
        if ($meetupDate) {
            $meetupDate->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }

        $rsvpDeadline = isset($data['rsvpDeadline']) ? new \DateTime($data['rsvpDeadline']) : null;
        if ($rsvpDeadline) {
            $rsvpDeadline->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }

        if ($rsvpDeadline && $meetupDate && $rsvpDeadline > $meetupDate) {
            throw new \LogicException($this->translator->trans('error.rsvp_deadline_after_meetup'));
        }

        $meetup = new Meetup();
        $meetup->setCompany($creator->getCompany());
        $meetup->setCreator($creator);
        $meetup->setTitle($data['title']);
        $meetup->setDescription($data['description'] ?? null);
        $meetup->setMeetupDate($meetupDate);
        $meetup->setLocation($data['location']);
        $meetup->setImageUrl($data['imageUrl'] ?? null);
        $meetup->setLink($data['link'] ?? null);
        $meetup->setMinParticipants($data['minParticipants'] ?? null);
        $meetup->setMaxParticipants($data['maxParticipants'] ?? null);
        $meetup->setRsvpDeadline($rsvpDeadline);

        $this->entityManager->persist($meetup);

        // Automatically add creator as a participant
        $rsvp = new MeetupRsvp();
        $rsvp->setCompany($creator->getCompany());
        $rsvp->setMeetup($meetup);
        $rsvp->setUser($creator);
        $rsvp->setStatus(RsvpStatus::GOING);
        $this->entityManager->persist($rsvp);

        $this->entityManager->flush();

        if ($data['sendNotification'] ?? false) {
            $this->notifyUsersOfNewMeetup($meetup);
        }

        return $meetup;
    }

    public function updateMeetup(Meetup $meetup, array $data, User $user): Meetup
    {
        if ($meetup->getCreator() !== $user && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw new AccessDeniedException('Only the creator or an admin can edit this meetup.');
        }

        if ($meetup->getMeetupDate() && new \DateTime() > $meetup->getMeetupDate()) {
            throw new \LogicException($this->translator->trans('error.cannot_edit_past_meetup'));
        }

        if (isset($data['title'])) {
            $meetup->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $meetup->setDescription($data['description']);
        }
        if (isset($data['location'])) {
            $meetup->setLocation($data['location']);
        }
        if (isset($data['imageUrl'])) {
            $meetup->setImageUrl($data['imageUrl']);
        }
        if (array_key_exists('meetupDate', $data)) {
            $newMeetupDate = $data['meetupDate'] ? new \DateTime($data['meetupDate']) : null;
            if ($newMeetupDate) {
                $newMeetupDate->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            }

            $currentDeadline = array_key_exists('rsvpDeadline', $data) ? ($data['rsvpDeadline'] ? new \DateTime($data['rsvpDeadline']) : null) : $meetup->getRsvpDeadline();
            if ($currentDeadline instanceof \DateTime) {
                $currentDeadline->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            }

            if ($currentDeadline && $newMeetupDate && $currentDeadline > $newMeetupDate) {
                throw new \LogicException($this->translator->trans('error.rsvp_deadline_after_meetup'));
            }
            $meetup->setMeetupDate($newMeetupDate);
        }

        if (array_key_exists('rsvpDeadline', $data)) {
            $newDeadline = $data['rsvpDeadline'] ? new \DateTime($data['rsvpDeadline']) : null;
            if ($newDeadline) {
                $newDeadline->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            }

            $currentMeetupDate = $meetup->getMeetupDate();
            if ($newDeadline && $currentMeetupDate && $newDeadline > $currentMeetupDate) {
                throw new \LogicException($this->translator->trans('error.rsvp_deadline_after_meetup'));
            }
            $meetup->setRsvpDeadline($newDeadline);
        }
        if (isset($data['link'])) {
            $meetup->setLink($data['link']);
        }
        if (isset($data['minParticipants'])) {
            $meetup->setMinParticipants($data['minParticipants']);
        }
        if (isset($data['maxParticipants'])) {
            $meetup->setMaxParticipants($data['maxParticipants']);
        }

        $this->entityManager->flush();

        return $meetup;
    }

    public function handleRsvp(Meetup $meetup, User $user, RsvpStatus $status): MeetupRsvp
    {
        // RSVP Freeze Logic
        if ($meetup->getRsvpDeadline() && new \DateTime() > $meetup->getRsvpDeadline()) {
            $this->evaluateMeetupStatus($meetup);
            throw new \LogicException($this->translator->trans('error.rsvp_window_closed'));
        }

        if (MeetupStatus::CANCELLED === $meetup->getStatus()) {
            throw new \LogicException($this->translator->trans('error.meetup_cancelled'));
        }

        $rsvpRepo = $this->entityManager->getRepository(MeetupRsvp::class);
        $rsvp = $rsvpRepo->findOneBy(['meetup' => $meetup, 'user' => $user]);
        $previousStatus = $rsvp?->getStatus();

        if (!$rsvp) {
            $rsvp = new MeetupRsvp();
            $rsvp->setCompany($user->getCompany());
            $rsvp->setMeetup($meetup);
            $rsvp->setUser($user);
            $this->entityManager->persist($rsvp);
        }

        if (RsvpStatus::GOING === $status) {
            if ($meetup->getMaxParticipants() && $meetup->getGoingCount() >= $meetup->getMaxParticipants() && RsvpStatus::GOING !== $previousStatus) {
                throw new \RuntimeException($this->translator->trans('error.meetup_full'));
            }
        }

        $rsvp->setStatus($status);
        $this->entityManager->flush();

        return $rsvp;
    }

    public function evaluateMeetupStatus(Meetup $meetup): void
    {
        if (MeetupStatus::OPEN !== $meetup->getStatus()) {
            return;
        }

        $goingCount = $meetup->getGoingCount();

        if ($meetup->getMinParticipants() && $goingCount < $meetup->getMinParticipants()) {
            $meetup->setStatus(MeetupStatus::CANCELLED);
            $this->notifyParticipantsOfCancellation($meetup);
            $this->declineAllParticipants($meetup);
        } else {
            $meetup->setStatus(MeetupStatus::CONFIRMED);
            $this->notifyParticipantsOfConfirmation($meetup);
        }
        $this->entityManager->flush();
    }

    public function cancelMeetup(Meetup $meetup, User $user): void
    {
        if ($meetup->getCreator() !== $user && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw new AccessDeniedException('Only the creator or an admin can cancel this meetup.');
        }

        $meetup->setStatus(MeetupStatus::CANCELLED);
        $this->notifyParticipantsOfCancellation($meetup);
        $this->declineAllParticipants($meetup);
        $this->entityManager->flush();
    }

    private function declineAllParticipants(Meetup $meetup): void
    {
        foreach ($meetup->getRsvps() as $rsvp) {
            if (RsvpStatus::GOING === $rsvp->getStatus()) {
                $rsvp->setStatus(RsvpStatus::NOT_GOING);
            }
        }
    }

    private function notifyUsersOfNewMeetup(Meetup $meetup): void
    {
        $userRepo = $this->entityManager->getRepository(User::class);
        $users = $userRepo->findBy(['company' => $meetup->getCompany(), 'isActive' => true]);

        $pushUsers = [];
        foreach ($users as $user) {
            if ($user === $meetup->getCreator()) {
                continue;
            }

            $this->emailService->sendNotificationEmailOnMeetup($user, $meetup);
            $pushUsers[] = $user;
        }

        if (!empty($pushUsers)) {
            $this->pushService->sendNotificationToUsers(
                $pushUsers,
                $this->translator->trans('push.meetup_created.title', ['%title%' => $meetup->getTitle()]),
                $this->translator->trans('push.meetup_created.body', ['%location%' => $meetup->getLocation()]),
                '/meetups'
            );
        }
    }

    private function getLoginUrl(): string
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';

        return $frontendUrl.'/login';
    }

    private function notifyParticipantsOfCancellation(Meetup $meetup): void
    {
        foreach ($meetup->getRsvps() as $rsvp) {
            if (RsvpStatus::GOING !== $rsvp->getStatus()) {
                continue;
            }

            $this->emailService->sendParticipantsOfCancellation($meetup, $rsvp);
        }
    }

    private function notifyParticipantsOfConfirmation(Meetup $meetup): void
    {
        foreach ($meetup->getRsvps() as $rsvp) {
            if (RsvpStatus::GOING !== $rsvp->getStatus()) {
                continue;
            }

            $this->emailService->sendParticipantsOfConfirmation($meetup, $rsvp);
        }
    }
}
