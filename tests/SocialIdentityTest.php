<?php

namespace Konsulting\Butler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SocialIdentityTest extends DatabaseTestCase
{
    #[Test]
    public function it_checks_if_the_confirmation_deadline_has_passed()
    {
        $pastDeadline = SocialIdentity::factory()->create(['confirm_until' => static::carbonNow()->subMinute()]);
        $notPastDeadline = SocialIdentity::factory()->create(['confirm_until' => static::carbonNow()->addMinute()]);
        $noDeadlineSet = SocialIdentity::factory()->create(['confirm_until' => null]);

        $this->assertTrue($pastDeadline->pastConfirmationDeadline());
        $this->assertFalse($notPastDeadline->pastConfirmationDeadline());
        $this->assertTrue($noDeadlineSet->pastConfirmationDeadline());
    }

    #[Test]
    #[DataProvider('accessTokenExpiryProvider')]
    public function it_checks_if_the_access_token_has_expired($expiresAt, $isExpired)
    {
        $socialIdentity = SocialIdentity::factory()->create(['expires_at' => $expiresAt])
            ->fresh();

        $this->assertSame($isExpired, $socialIdentity->accessTokenIsExpired());
    }

    public static function accessTokenExpiryProvider()
    {
        return [
            [static::carbonNow()->subMonth(), true],
            [static::carbonNow()->addMonth(), false],
            [null, true],
        ];
    }
}
