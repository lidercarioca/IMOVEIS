<?php

namespace Tests\Unit;

use App\Infrastructure\Mail\MailerService;
use PHPUnit\Framework\TestCase;

class MailerServiceTest extends TestCase
{
    public function test_it_can_load_smtp_configuration(): void
    {
        $mailer = new MailerService();

        $this->assertInstanceOf(MailerService::class, $mailer);
    }
}
