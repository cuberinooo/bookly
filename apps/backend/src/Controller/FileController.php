<?php

declare(strict_types=1);

namespace App\Controller;

use Aws\S3\S3ClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FileController extends AbstractController
{
    public function __construct(
        private S3ClientInterface $s3Client,
        private string $s3Bucket
    ) {
    }

    #[Route('/uploads/{path}', name: 'uploads_serve', requirements: ['path' => '.+'], methods: ['GET'])]
    public function serve(string $path): Response
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $path,
            ]);
        } catch (\Aws\S3\Exception\S3Exception $e) {
            // If the file doesn't exist in the bucket, gracefully throw a 404
            throw $this->createNotFoundException('File not found in storage.', $e);
        }

        // 2. Read the body content
        $content = $result['Body']->getContents();
        $contentType = $result['ContentType'] ?? 'application/octet-stream';

        // 3. Return a standard response with the file content and correct mime type
        return new Response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
