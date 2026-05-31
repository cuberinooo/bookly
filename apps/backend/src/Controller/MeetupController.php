<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Meetup;
use App\Enum\RsvpStatus;
use App\Repository\MeetupCommentRepository;
use App\Repository\MeetupRepository;
use App\Service\ApiCacheService;
use App\Service\MeetupService;
use Aws\S3\S3ClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/meetups')]
class MeetupController extends AbstractController
{
    public function __construct(
        private S3ClientInterface $s3Client,
        private string $s3Bucket,
        private SluggerInterface $slugger,
        private readonly ApiCacheService $apiCache,
        private readonly MeetupCommentRepository $commentRepository,
    ) {
    }

    #[Route('/upload-image', name: 'meetup_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $company = $user->getCompany();
        $companySlug = $this->slugger->slug($company->getName())->lower();

        $extension = $file->guessExtension() ?? 'jpg';
        $filename = sprintf('meetup_%s.%s', uniqid('', true), $extension);
        $key = $companySlug.'/meetups/'.$filename;

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $key,
                'Body'   => fopen($file->getRealPath(), 'r'),
                'ContentType' => $file->getClientMimeType(),
            ]);

            // Build the absolute URL using the current request
            $baseUrl = $request->getSchemeAndHttpHost();
            $url = sprintf('%s/uploads/%s', $baseUrl, $key);

            return new JsonResponse(['url' => $url, 'path' => $key]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to upload image: '.$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'meetup_index', methods: ['GET'])]
    public function index(Request $request, MeetupRepository $meetupRepository, SerializerInterface $serializer): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $companyId = $user->getCompany()->getId();
        $filter = $request->query->get('filter');

        $context = [
            'filter' => $filter,
            'userId' => $user->getId(),
        ];

        $data = $this->apiCache->get('meetup', $companyId, $context, function () use ($filter, $meetupRepository, $serializer, $user) {
            $qb = $meetupRepository->createQueryBuilder('m');

            if ('active' === $filter) {
                $qb->andWhere($qb->expr()->orX(
                    'm.meetupDate IS NULL',
                    'm.meetupDate >= :now'
                ))
                ->andWhere('m.status != :cancelled')
                ->setParameter('now', new \DateTime())
                ->setParameter('cancelled', \App\Enum\MeetupStatus::CANCELLED);
            } elseif ('past' === $filter) {
                $qb->andWhere('m.meetupDate < :now')
                ->andWhere('m.status != :cancelled')
                ->setParameter('now', new \DateTime())
                ->setParameter('cancelled', \App\Enum\MeetupStatus::CANCELLED);
            } elseif ('cancelled' === $filter) {
                $qb->andWhere('m.status = :cancelled')
                ->setParameter('cancelled', \App\Enum\MeetupStatus::CANCELLED);
            } elseif ('joined' === $filter) {
                $qb->join('m.rsvps', 'r')
                ->andWhere('r.user = :user')
                ->andWhere('r.status = :status')
                ->andWhere('m.status != :cancelled')
                ->setParameter('user', $user)
                ->setParameter('status', RsvpStatus::GOING)
                ->setParameter('cancelled', \App\Enum\MeetupStatus::CANCELLED);
            }

            $meetups = $qb->orderBy('m.meetupDate', 'ASC')
                ->addOrderBy('m.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            $meetupsArray = json_decode($serializer->serialize($meetups, 'json', ['groups' => 'meetup:read']), true);

            foreach ($meetupsArray as $index => $meetupData) {
                $meetup = $meetups[$index];
                $meetupsArray[$index]['unreadCommentsCount'] = $this->commentRepository->countUnreadComments($user, $meetup);
            }

            return $meetupsArray;
        }, 300);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/notifications', name: 'meetup_notifications', methods: ['GET'])]
    public function notifications(MeetupRepository $meetupRepository): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Get all active meetups (where comments can be relevant)
        $qb = $meetupRepository->createQueryBuilder('m');
        $qb->andWhere($qb->expr()->orX(
               'm.meetupDate IS NULL',
               'm.meetupDate >= :now'
           ))
           ->andWhere('m.status != :cancelled')
           ->setParameter('now', new \DateTime())
           ->setParameter('cancelled', \App\Enum\MeetupStatus::CANCELLED);

        $relevantMeetups = $qb->getQuery()->getResult();
        $totalUnread = 0;

        foreach ($relevantMeetups as $meetup) {
            $totalUnread += $this->commentRepository->countUnreadComments($user, $meetup);
        }

        return new JsonResponse(['totalUnread' => $totalUnread], Response::HTTP_OK);
    }

    #[Route('', name: 'meetup_new', methods: ['POST'])]
    public function new(Request $request, MeetupService $meetupService, SerializerInterface $serializer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        try {
            $meetup = $meetupService->createMeetup($data, $user);
            $json = $serializer->serialize($meetup, 'json', ['groups' => 'meetup:read']);
            $meetupData = json_decode($json, true);
            $meetupData['unreadCommentsCount'] = 0;

            return new JsonResponse($meetupData, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'meetup_show', methods: ['GET'])]
    public function show(Meetup $meetup, SerializerInterface $serializer): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $companyId = $user->getCompany()->getId();

        $context = [
            'id' => $meetup->getId(),
            'userId' => $user->getId(),
        ];

        $data = $this->apiCache->get('meetup', $companyId, $context, function () use ($meetup, $serializer, $user) {
            $json = $serializer->serialize($meetup, 'json', ['groups' => 'meetup:read']);
            $meetupData = json_decode($json, true);
            $meetupData['unreadCommentsCount'] = $this->commentRepository->countUnreadComments($user, $meetup);

            return $meetupData;
        }, 300);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'meetup_edit', methods: ['PUT'])]
    public function edit(Request $request, Meetup $meetup, MeetupService $meetupService, SerializerInterface $serializer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        try {
            $meetup = $meetupService->updateMeetup($meetup, $data, $user);
            $json = $serializer->serialize($meetup, 'json', ['groups' => 'meetup:read']);

            return new JsonResponse(json_decode($json, true), Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/rsvp', name: 'meetup_rsvp', methods: ['POST'])]
    public function rsvp(Request $request, Meetup $meetup, MeetupService $meetupService, SerializerInterface $serializer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $statusStr = $data['status'] ?? 'GOING';
        $status = RsvpStatus::from(strtolower($statusStr));

        try {
            $rsvp = $meetupService->handleRsvp($meetup, $user, $status);
            $json = $serializer->serialize($rsvp, 'json', ['groups' => 'rsvp:read']);

            return new JsonResponse(json_decode($json, true), Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/cancel', name: 'meetup_cancel', methods: ['POST'])]
    public function cancel(Meetup $meetup, MeetupService $meetupService): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        try {
            $meetupService->cancelMeetup($meetup, $user);

            return new JsonResponse(['status' => 'cancelled'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
