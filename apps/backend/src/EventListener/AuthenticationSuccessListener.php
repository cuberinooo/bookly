<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\Cookie;

class AuthenticationSuccessListener
{
    private int $tokenTtl;
    private ?string $cookieDomain;

    public function __construct(int $tokenTtl, ?string $cookieDomain = null)
    {
        $this->tokenTtl = $tokenTtl;
        $this->cookieDomain = $cookieDomain;
    }

    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $response = $event->getResponse();

        if (isset($data['refresh_token'])) {
            $refreshToken = $data['refresh_token'];
            
            // Remove refresh token from response body
            unset($data['refresh_token']);
            $event->setData($data);

            // Add refresh token to HttpOnly cookie
            $response->headers->setCookie(
                new Cookie(
                    'refresh_token',
                    $refreshToken,
                    time() + $this->tokenTtl,
                    '/api/token/refresh', // Only send to refresh endpoint
                    $this->cookieDomain,
                    true, // Secure
                    true, // HttpOnly
                    false,
                    'lax' // SameSite
                )
            );
        }
    }
}
