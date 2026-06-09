<?php

declare(strict_types=1);

namespace App\Controller;

use Aws\S3\S3ClientInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileController extends AbstractController
{
    public function __construct(
        private S3ClientInterface $s3Client,
        private string $s3Bucket,
        private JWTEncoderInterface $jwtEncoder,
        private SluggerInterface $slugger
    ) {
    }

    #[Route('/uploads/{path}', name: 'uploads_serve', requirements: ['path' => '.+'], methods: ['GET'])]
    public function serve(string $path, Request $request): Response
    {
        // 1. Authenticate user via JWT (Header or Query Parameter)
        $authHeader = $request->headers->get('Authorization');
        $token = null;
        if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            $token = $request->query->get('token');
        }

        if (!$token) {
            return new Response('Access Denied. Authentication token missing.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $this->jwtEncoder->decode($token);
        } catch (\Exception $e) {
            return new Response('Access Denied. Invalid or expired authentication token.', Response::HTTP_FORBIDDEN);
        }

        $companyName = $payload['companyName'] ?? null;
        if (!$companyName) {
            return new Response('Access Denied. No company associated with token.', Response::HTTP_FORBIDDEN);
        }

        $companySlug = $this->slugger->slug($companyName)->lower()->toString();

        // 2. Verify that the user's company matches the requested path company
        $parts = explode('/', $path);
        $pathCompanySlug = $parts[0] ?? '';

        if ($pathCompanySlug !== $companySlug) {
            return new Response('Access Denied. You cannot access files of other companies.', Response::HTTP_FORBIDDEN);
        }

        // 3. Retrieve and stream the file
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $path,
            ]);
        } catch (\Aws\S3\Exception\S3Exception $e) {
            // If the file doesn't exist in the bucket, gracefully throw a 404
            throw $this->createNotFoundException('File not found in storage.', $e);
        }

        // 4. Read the body content
        $content = $result['Body']->getContents();
        $contentType = $result['ContentType'] ?? 'application/octet-stream';

        // 5. Return a standard response with the file content and correct mime type
        return new Response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
