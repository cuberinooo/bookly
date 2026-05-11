<?php

namespace App\Service;

use App\Entity\Meetup;
use App\Entity\MeetupRsvp;
use App\Entity\User;
use App\Enum\MeetupStatus;
use App\Enum\RsvpStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class MeetupService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {}

    public function createMeetup(array $data, User $creator): Meetup
    {
        $meetup = new Meetup();
        $meetup->setCompany($creator->getCompany());
        $meetup->setCreator($creator);
        $meetup->setTitle($data['title']);
        $meetup->setDescription($data['description'] ?? null);
        $meetup->setMeetupDate(new \DateTime($data['meetupDate']));
        $meetup->setLocation($data['location']);
        $meetup->setImageUrl($data['imageUrl'] ?? null);
        $meetup->setLink($data['link'] ?? null);
        $meetup->setMinParticipants($data['minParticipants'] ?? null);
        $meetup->setMaxParticipants($data['maxParticipants'] ?? null);
        $meetup->setRsvpDeadline(new \DateTime($data['rsvpDeadline']));

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
        if ($meetup->getCreator() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            throw new AccessDeniedException("Only the creator or an admin can edit this meetup.");
        }

        if (new \DateTime() > $meetup->getMeetupDate()) {
            throw new \LogicException("Cannot edit a past meetup.");
        }

        if (isset($data['title'])) $meetup->setTitle($data['title']);
        if (isset($data['description'])) $meetup->setDescription($data['description']);
        if (isset($data['location'])) $meetup->setLocation($data['location']);
        if (isset($data['imageUrl'])) $meetup->setImageUrl($data['imageUrl']);
        if (isset($data['meetupDate'])) $meetup->setMeetupDate(new \DateTime($data['meetupDate']));
        if (isset($data['rsvpDeadline'])) $meetup->setRsvpDeadline(new \DateTime($data['rsvpDeadline']));
        if (isset($data['link'])) $meetup->setLink($data['link']);
        if (isset($data['minParticipants'])) $meetup->setMinParticipants($data['minParticipants']);
        if (isset($data['maxParticipants'])) $meetup->setMaxParticipants($data['maxParticipants']);

        $this->entityManager->flush();

        return $meetup;
    }

    public function handleRsvp(Meetup $meetup, User $user, RsvpStatus $status): MeetupRsvp
    {
        // RSVP Freeze Logic
        if (new \DateTime() > $meetup->getRsvpDeadline()) {
            $this->evaluateMeetupStatus($meetup);
            throw new \LogicException("The RSVP window is closed.");
        }

        if ($meetup->getStatus() === MeetupStatus::CANCELLED) {
             throw new \LogicException("This meetup has been cancelled.");
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

        if ($status === RsvpStatus::GOING) {
            if ($meetup->getMaxParticipants() && $meetup->getGoingCount() >= $meetup->getMaxParticipants() && $previousStatus !== RsvpStatus::GOING) {
                throw new \RuntimeException("Meetup is full.");
            }
        }

        $rsvp->setStatus($status);
        $this->entityManager->flush();

        return $rsvp;
    }

    public function evaluateMeetupStatus(Meetup $meetup): void
    {
        if ($meetup->getStatus() !== MeetupStatus::OPEN) return;

        $goingCount = $meetup->getGoingCount();

        if ($meetup->getMinParticipants() && $goingCount < $meetup->getMinParticipants()) {
            $meetup->setStatus(MeetupStatus::CANCELLED);
            $this->declineAllParticipants($meetup);
            $this->notifyParticipantsOfCancellation($meetup);
        } else {
            $meetup->setStatus(MeetupStatus::CONFIRMED);
            $this->notifyParticipantsOfConfirmation($meetup);
        }
        $this->entityManager->flush();
    }

    public function cancelMeetup(Meetup $meetup, User $user): void
    {
        if ($meetup->getCreator() !== $user && !in_array('ROLE_ADMIN', $user->getRoles())) {
            throw new AccessDeniedException("Only the creator or an admin can cancel this meetup.");
        }

        $meetup->setStatus(MeetupStatus::CANCELLED);
        $this->declineAllParticipants($meetup);
        $this->notifyParticipantsOfCancellation($meetup);
        $this->entityManager->flush();
    }

    private function declineAllParticipants(Meetup $meetup): void
    {
        foreach ($meetup->getRsvps() as $rsvp) {
            if ($rsvp->getStatus() === RsvpStatus::GOING) {
                $rsvp->setStatus(RsvpStatus::NOT_GOING);
            }
        }
    }

    private function notifyUsersOfNewMeetup(Meetup $meetup): void
    {
        $userRepo = $this->entityManager->getRepository(User::class);
        $users = $userRepo->findBy(['company' => $meetup->getCompany(), 'isActive' => true]);

        foreach ($users as $user) {
            if ($user === $meetup->getCreator()) continue;

            $email = (new TemplatedEmail())
                ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $meetup->getCompany()->getName()))
                ->to($user->getEmail())
                ->subject(sprintf('New Meetup: %s', $meetup->getTitle()))
                ->htmlTemplate('emails/meetup_invitation.html.twig')
                ->context([
                    'meetup' => $meetup,
                    'user' => $user,
                    'siteName' => $meetup->getCompany()->getName(),
                    'loginUrl' => $this->getLoginUrl(),
                ]);

            $this->mailer->send($email);
        }
    }

    private function getLoginUrl(): string
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:4200';
        return $frontendUrl . '/login';
    }

    private function notifyParticipantsOfCancellation(Meetup $meetup): void
    {
        foreach ($meetup->getRsvps() as $rsvp) {
            if ($rsvp->getStatus() !== RsvpStatus::GOING) continue;

            $email = (new TemplatedEmail())
                ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $meetup->getCompany()->getName()))
                ->to($rsvp->getUser()->getEmail())
                ->subject(sprintf('Meetup Cancelled: %s', $meetup->getTitle()))
                ->htmlTemplate('emails/meetup_cancellation.html.twig')
                ->context([
                    'meetup' => $meetup,
                    'user' => $rsvp->getUser(),
                    'siteName' => $meetup->getCompany()->getName(),
                    'loginUrl' => $this->getLoginUrl(),
                ]);

            $this->mailer->send($email);
        }
    }

    private function notifyParticipantsOfConfirmation(Meetup $meetup): void
    {
        foreach ($meetup->getRsvps() as $rsvp) {
            if ($rsvp->getStatus() !== RsvpStatus::GOING) continue;

            $email = (new TemplatedEmail())
                ->from(new Address($_ENV['NO_REPLY_MAIL'] ?? 'noreply@example.com', $meetup->getCompany()->getName()))
                ->to($rsvp->getUser()->getEmail())
                ->subject(sprintf('Meetup Confirmed: %s', $meetup->getTitle()))
                ->htmlTemplate('emails/meetup_confirmation.html.twig')
                ->context([
                    'meetup' => $meetup,
                    'user' => $rsvp->getUser(),
                    'siteName' => $meetup->getCompany()->getName(),
                    'loginUrl' => $this->getLoginUrl(),
                ]);

            $this->mailer->send($email);
        }
    }
}
