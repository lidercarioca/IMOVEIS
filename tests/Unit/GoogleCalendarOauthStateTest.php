<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../google/google_oauth_state.php';

class GoogleCalendarOauthStateTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    public function testStateValidAcceptsMatchingOwner(): void
    {
        $payload = google_calendar_build_oauth_state(12, 99, 600);

        $this->assertTrue(google_calendar_is_valid_oauth_state($payload['state'], 12, 99, 600));
    }

    public function testStateExpiredRejects(): void
    {
        $payload = google_calendar_build_oauth_state(12, 99, 1);
        $_SESSION['google_oauth_state_payload']['expires_at'] = time() - 1;

        $this->assertFalse(google_calendar_is_valid_oauth_state($payload['state'], 12, 99, 1));
    }

    public function testStateMissingRejects(): void
    {
        $this->assertFalse(google_calendar_is_valid_oauth_state('', 12, 99));
    }

    public function testStateReusedRejects(): void
    {
        $payload = google_calendar_build_oauth_state(12, 99, 600);
        google_calendar_consume_oauth_state();

        $this->assertFalse(google_calendar_is_valid_oauth_state($payload['state'], 12, 99, 600));
    }

    public function testStateFromOtherUserRejects(): void
    {
        $payload = google_calendar_build_oauth_state(12, 99, 600);

        $this->assertFalse(google_calendar_is_valid_oauth_state($payload['state'], 12, 98, 600));
    }

    public function testStateFromOtherTenantRejects(): void
    {
        $payload = google_calendar_build_oauth_state(12, 99, 600);

        $this->assertFalse(google_calendar_is_valid_oauth_state($payload['state'], 11, 99, 600));
    }
}
