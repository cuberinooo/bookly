<?php

namespace App\Controller;

use App\Enum\BookingWindow;
use App\Repository\GlobalSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/settings')]
class GlobalSettingsController extends AbstractController
{
    #[Route('', name: 'settings_get', methods: ['GET'])]
    public function getSettings(GlobalSettingsRepository $repository, SerializerInterface $serializer): JsonResponse
    {
        $settings = $repository->get();
        $json = $serializer->serialize($settings, 'json', ['groups' => 'settings:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'settings_update', methods: ['PATCH'])]
    public function updateSettings(
        Request $request, 
        GlobalSettingsRepository $repository, 
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_TRAINER');

        $settings = $repository->get();
        $data = json_decode($request->getContent(), true);

        if (isset($data['showParticipantNames'])) {
            $settings->setShowParticipantNames((bool) $data['showParticipantNames']);
        }
        if (isset($data['isWaitlistVisible'])) {
            $settings->setWaitlistVisible((bool) $data['isWaitlistVisible']);
        }
        if (isset($data['bookingWindow'])) {
            $window = BookingWindow::tryFrom($data['bookingWindow']);
            if ($window) {
                $settings->setBookingWindow($window);
            }
        }
        
        // Legal settings
        if (array_key_exists('legalNoticeCompanyName', $data)) {
            $settings->setLegalNoticeCompanyName($data['legalNoticeCompanyName']);
        }
        if (array_key_exists('legalNoticeRepresentative', $data)) {
            $settings->setLegalNoticeRepresentative($data['legalNoticeRepresentative']);
        }
        if (array_key_exists('legalNoticeStreet', $data)) {
            $settings->setLegalNoticeStreet($data['legalNoticeStreet']);
        }
        if (array_key_exists('legalNoticeHouseNumber', $data)) {
            $settings->setLegalNoticeHouseNumber($data['legalNoticeHouseNumber']);
        }
        if (array_key_exists('legalNoticeZipCode', $data)) {
            $settings->setLegalNoticeZipCode($data['legalNoticeZipCode']);
        }
        if (array_key_exists('legalNoticeCity', $data)) {
            $settings->setLegalNoticeCity($data['legalNoticeCity']);
        }
        if (array_key_exists('legalNoticeEmail', $data)) {
            $settings->setLegalNoticeEmail($data['legalNoticeEmail']);
        }
        if (array_key_exists('legalNoticePhone', $data)) {
            $settings->setLegalNoticePhone($data['legalNoticePhone']);
        }
        if (array_key_exists('legalNoticeTaxId', $data)) {
            $settings->setLegalNoticeTaxId($data['legalNoticeTaxId']);
        }
        if (array_key_exists('legalNoticeVatId', $data)) {
            $settings->setLegalNoticeVatId($data['legalNoticeVatId']);
        }

        $entityManager->flush();

        return new JsonResponse(['status' => 'Global settings updated']);
    }

    #[Route('/privacy-policy', name: 'settings_upload_privacy_policy', methods: ['POST'])]
    public function uploadPrivacyPolicy(
        Request $request,
        GlobalSettingsRepository $repository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move(
                $this->getParameter('kernel.project_dir').'/public/uploads/legal',
                $newFilename
            );
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Failed to upload file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $settings = $repository->get();
        $settings->setPrivacyPolicyPdfPath('/uploads/legal/'.$newFilename);
        $entityManager->flush();

        return new JsonResponse(['path' => $settings->getPrivacyPolicyPdfPath()]);
    }
}
