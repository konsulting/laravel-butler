<?php

namespace Konsulting\Butler;

use Butler;
use Notification;
use Konsulting\Butler\Fake\User;
use Konsulting\Butler\Fake\Socialite;
use Konsulting\Butler\Notifications\ConfirmSocialIdentity;

class AuthControllerTest extends DatabaseTestCase
{
    public function test_it_redirects_to_a_provider()
    {
        $this->visitRoute('butler.redirect', 'test');
        $this->see('Redirect to location for test');
    }

    public function test_it_does_not_redirect_to_an_unknown_provider()
    {
        $this->visitRoute('butler.redirect', 'IdontExist');
        $this->seeRouteIs(Butler::routeName('login'));
    }

    public function test_it_authenticates_a_valid_identity()
    {
        $user = $this->makeUser();
        $identity = $this->makeIdentity();
        $this->makeConfirmedSocialIdentity('test', $user, $identity);

        $this->visitRoute('butler.callback', 'test');
        $this->seeRouteIs(Butler::routeName('authenticated'));
    }

    public function test_it_doesnt_authenticate_an_unknown_identity()
    {
        $this->visitRoute('butler.callback', 'test');
        $this->seeRouteIs(Butler::routeName('login'));
    }

    public function test_it_creates_a_user_if_allowed_on_callback()
    {
        Notification::fake();

        $this->allowUserCreation();

        $this->visitRoute('butler.callback', 'test');
        $this->seeRouteIs(Butler::routeName('login'));
        $this->seeInDatabase('users', ['email' => 'keoghan@klever.co.uk']);

        Notification::assertSentTo(
            User::first(),
            ConfirmSocialIdentity::class
        );
    }

    public function test_it_creates_a_user_if_allowed_on_callback_but_doesnt_notify_if_not_needed()
    {
        config()->set('butler.confirm_identity_for_new_user', false);

        Notification::fake();

        $this->allowUserCreation();

        $this->visitRoute('butler.callback', 'test');
        $this->seeRouteIs(Butler::routeName('profile'));
        $this->seeInDatabase('users', ['email' => 'keoghan@klever.co.uk']);

        Notification::assertNotSentTo(
            User::first(),
            ConfirmSocialIdentity::class
        );
    }

    public function test_it_DOESNT_create_a_user_if_not_allowed_on_callback()
    {
        $this->stopUserCreation();

        $this->visitRoute('butler.callback', 'test');
        $this->seeRouteIs(Butler::routeName('login'));
        $this->dontSeeInDatabase('users', ['email' => 'keoghan@klever.co.uk']);
    }

    public function test_an_authenticated_user_can_add_identities()
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->visitRoute('butler.callback', 'test')
            ->seeRouteIs(Butler::routeName('profile'))
            ->seeInDatabase('social_identities', ['user_id' => 1]);
    }

    public function test_it_can_confirm_an_identity()
    {
        $user = $this->makeUser();
        $identity = $this->makeIdentity();
        $socialIdentity = SocialIdentity::createFromOauthIdentity('test', $user, $identity);

        $this->visitRoute('butler.confirm', $socialIdentity->confirm_token);
        $this->seeRouteIs(Butler::routeName('login'));
        $this->assertNotNull($socialIdentity->fresh()->confirmed_at);
    }

    public function test_it_redirects_an_authenticated_user_to_profile()
    {
        $user = $this->makeUser();
        $identity = $this->makeIdentity();
        $socialIdentity = SocialIdentity::createFromOauthIdentity('test', $user, $identity);

        $this->actingAs($user);
        $this->visitRoute('butler.confirm', $socialIdentity->confirm_token);
        $this->seeRouteIs(Butler::routeName('profile'));
        $this->assertNotNull($socialIdentity->fresh()->confirmed_at);
    }

    public function test_logging_in_with_the_an_existing_identity_on_your_account_when_already_authenticated_will_redirect_you_to_profile()
    {
        $user = $this->makeUser();
        $identity = $this->makeIdentity();
        $this->makeConfirmedSocialIdentity('test', $user, $identity);

        $this->actingAs($user);
        $this->visitRoute('butler.callback', 'test');
        $this->seeRouteIs(Butler::routeName('profile'));
        $this->dontSee('Identity saved');
    }

    public function setUp()
    {
        parent::setUp();

        $this->app->singleton('\Laravel\Socialite\Contracts\Factory', Socialite::class);
    }

    public function makeUser2()
    {
        return User::create(['name' => 'Roger', 'email' => 'roger@klever.co.uk']);
    }
}
