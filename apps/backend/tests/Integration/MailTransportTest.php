<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailTransportTest extends KernelTestCase
{
    /**
     * This test will attempt to send a real email using the configured MAILER_DSN.
     * It is intended for manual verification of the Resend integration.
     */
    public function testResendSending(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $mailer = $container->get(MailerInterface::class);

        $dsn = $_ENV['MAILER_DSN'] ?? '';

        if (str_contains($dsn, 'KEY') || empty($dsn)) {
            $this->markTestSkipped('MAILER_DSN is not configured with a real API key. Update .env.local with a real key.');
        }

        $from = $_ENV['NO_REPLY_MAIL'] ?? 'onboarding@resend.dev';
        // IMPORTANT: For Resend testing mode, 'to' MUST be your verified email
        $to = 'kubilay.anil@codingcube.de'; 

        fwrite(STDERR, "\nSending test email via Resend:\n");
        fwrite(STDERR, "  From: $from\n");
        fwrite(STDERR, "  To:   $to\n");
        fwrite(STDERR, "  Note: If this hangs or 'succeeds' without an email, check if 'php bin/console messenger:consume async' is running.\n");

        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('Resend Integration Test - ' . date('Y-m-d H:i:s'))
            ->text('This is a test email from the Phoenix Booking App integration test.')
            ->html('<p>This is a test email from the <strong>Phoenix Booking App</strong> integration test.</p>');

        // Force synchronous sending for this test by using a custom header
        $email->getHeaders()->addTextHeader('X-Transport', 'resend');

        try {
            $mailer->send($email);
            $this->assertTrue(true, 'Email sent successfully via Resend');
        } catch (\Exception $e) {
            $this->fail('Failed to send email via Resend: ' . $e->getMessage());
        }
    }
    public function testTransportIsResend(): void
    {
        self::bootKernel();

        $dsn = $_ENV['MAILER_DSN'] ?? '';
        $maskedDsn = preg_replace('/(?<=:\/\/).*(?=@)/', '********', $dsn);

        fwrite(STDERR, "\nChecking Mailer DSN:\n");
        fwrite(STDERR, "  DSN: $maskedDsn\n");

        $this->assertStringStartsWith('resend+api://', $dsn, 'MAILER_DSN should use resend+api:// scheme');
    }}
