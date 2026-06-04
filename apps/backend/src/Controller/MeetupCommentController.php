<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Meetup;
use App\Entity\MeetupComment;
use App\Entity\MeetupUserReadState;
use App\Repository\MeetupCommentRepository;
use App\Repository\MeetupUserReadStateRepository;
use App\Service\ApiCacheService;
use App\Service\MercurePublisherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/meetups/{id}/comments')]
class MeetupCommentController extends AbstractController
{
    public function __construct(
        private readonly ApiCacheService $apiCache,
        private readonly MercurePublisherService $mercurePublisher,
    ) {
    }

    #[Route('', name: 'meetup_comments_index', methods: ['GET'])]
    public function index(Meetup $meetup, MeetupCommentRepository $commentRepository, SerializerInterface $serializer): JsonResponse
    {
        $comments = $commentRepository->findBy(['meetup' => $meetup], ['createdAt' => 'ASC']);
        $json = $serializer->serialize($comments, 'json', ['groups' => 'comment:read']);

        return new JsonResponse(json_decode($json, true), Response::HTTP_OK);
    }

    #[Route('', name: 'meetup_comments_new', methods: ['POST'])]
    public function new(Request $request, Meetup $meetup, EntityManagerInterface $entityManager, SerializerInterface $serializer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!isset($data['content']) || empty(trim($data['content']))) {
            return new JsonResponse(['error' => 'Comment content cannot be empty'], Response::HTTP_BAD_REQUEST);
        }

        $comment = new MeetupComment();
        $comment->setMeetup($meetup);
        $comment->setAuthor($user);
        $comment->setContent($data['content']);
        $comment->setCompany($user->getCompany());

        $entityManager->persist($comment);
        $entityManager->flush();

        $this->apiCache->invalidateEntity('meetup', $user->getCompany()->getId());

        $this->mercurePublisher->publishEntityUpdate($comment, 'created', ['meetupId' => $meetup->getId()]);
        // Also trigger an update for the meetup itself so participants get refreshed unread counts
        $this->mercurePublisher->publishEntityUpdate($meetup, 'updated');

        $json = $serializer->serialize($comment, 'json', ['groups' => 'comment:read']);

        return new JsonResponse(json_decode($json, true), Response::HTTP_CREATED);
    }

    #[Route('/mark-read', name: 'meetup_comments_mark_read', methods: ['POST'])]
    public function markRead(Meetup $meetup, MeetupUserReadStateRepository $readStateRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        try {
            $readState = $readStateRepository->findOneBy(['meetup' => $meetup, 'user' => $user]);

            if (!$readState) {
                $readState = new MeetupUserReadState();
                $readState->setMeetup($meetup);
                $readState->setUser($user);
                $readState->setCompany($user->getCompany());
                $entityManager->persist($readState);
            }

            $readState->setLastReadAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->mercurePublisher->publishEntityUpdate($readState, 'updated', ['meetupId' => $meetup->getId()]);
            $this->mercurePublisher->publishEntityUpdate($meetup, 'updated');
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Another process created it simultaneously. Clear and retry the update.
            $entityManager->clear();
            $user = $entityManager->getRepository(\App\Entity\User::class)->find($user->getId());
            $meetup = $entityManager->getRepository(Meetup::class)->find($meetup->getId());
            $readState = $readStateRepository->findOneBy(['meetup' => $meetup, 'user' => $user]);
            if ($readState) {
                $readState->setLastReadAt(new \DateTimeImmutable());
                $entityManager->flush();
                $this->mercurePublisher->publishEntityUpdate($readState, 'updated', ['meetupId' => $meetup->getId()]);
                $this->mercurePublisher->publishEntityUpdate($meetup, 'updated');
            }
        }

        $this->apiCache->invalidateEntity('meetup', $user->getCompany()->getId());

        return new JsonResponse(['status' => 'success'], Response::HTTP_OK);
    }
}
