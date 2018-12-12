<?php

namespace Konsulting\Butler;

class SocialIdentityTest extends DatabaseTestCase
{
    /** @test */
    public function it_checks_if_the_confirmation_deadline_has_passed()
    {
        $pastDeadline = factory(SocialIdentity::class)->create(['confirm_until' => $this->carbonNow->subMinute()]);
        $notPastDeadline = factory(SocialIdentity::class)->create(['confirm_until' => $this->carbonNow->addMinute()]);
        $noDeadlineSet = factory(SocialIdentity::class)->create(['confirm_until' => null]);

        $this->assertTrue($pastDeadline->pastConfirmationDeadline());
        $this->assertFalse($notPastDeadline->pastConfirmationDeadline());
        $this->assertTrue($noDeadlineSet->pastConfirmationDeadline());
    }
}
