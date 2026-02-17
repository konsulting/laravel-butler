<?php

namespace Konsulting\Butler;

class SocialIdentityTest extends DatabaseTestCase
{
    /** @test */
    public function it_checks_if_the_confirmation_deadline_has_passed()
    {
        $pastDeadline = factory(SocialIdentity::class)->create(['confirm_until' => static::carbonNow()->subMinute()]);
        $notPastDeadline = factory(SocialIdentity::class)->create(['confirm_until' => static::carbonNow()->addMinute()]);
        $noDeadlineSet = factory(SocialIdentity::class)->create(['confirm_until' => null]);

        $this->assertTrue($pastDeadline->pastConfirmationDeadline());
        $this->assertFalse($notPastDeadline->pastConfirmationDeadline());
        $this->assertTrue($noDeadlineSet->pastConfirmationDeadline());
    }

    /**
     * @test
     *
     * @dataProvider accessTokenExpiryProvider
     */
    public function it_checks_if_the_access_token_has_expired($expiresAt, $isExpired)
    {
        $socialIdentity = factory(SocialIdentity::class)->create(['expires_at' => $expiresAt])
            ->fresh();

        $this->assertSame($isExpired, $socialIdentity->accessTokenIsExpired());
    }

    public function accessTokenExpiryProvider()
    {
        return [
            [static::carbonNow()->subMonth(), true],
            [static::carbonNow()->addMonth(), false],
            [null, true],
        ];
    }
}
