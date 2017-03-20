<?php

namespace Konsulting\Butler;

use Butler;
use Konsulting\Butler\Fake\User;
use Konsulting\Butler\Exceptions\NoUser;
use Konsulting\Butler\Exceptions\UnknownProvider;
use Konsulting\Butler\Exceptions\SocialIdentityAlreadyAssociated;

class BasicButlerTest extends TestCase
{
    public function test_providers_are_set_from_config()
    {
        $providers = Butler::providers();

        $this->assertEquals(collect(['test']), $providers->keys());
    }

    public function test_it_checks_providers()
    {
        Butler::checkProvider('test');

        $this->expectException(UnknownProvider::class);
        $this->assertFalse(Butler::checkProvider('IdontExist'));
    }

    public function test_it_returns_route_names()
    {
        $this->assertEquals('login', Butler::routeName('login'));
    }

    public function test_it_can_authenticate_a_user_for_a_known_identity()
    {
        $user = $this->makeUser();
        $identity = $this->makeIdentity();
        $this->makeConfirmedSocialIdentity('test', $user, $identity);

        $this->assertTrue(Butler::authenticate('test', $identity));
        $this->assertEquals(auth()->user()->id, $user->id);
    }

    public function test_it_does_not_authenticate_an_unknown_identity()
    {
        $identity = $this->makeIdentity();

        $this->assertFalse(Butler::authenticate('test', $identity));
        $this->assertFalse(auth()->check());
    }

    public function test_it_will_create_a_user_if_allowed()
    {
        $identity = $this->makeIdentity();

        $this->allowUserCreation();

        Butler::register('test', $identity);

        $this->seeInDatabase('users', ['email' => 'keoghan@klever.co.uk']);
    }

    public function test_it_will_NOT_create_a_user_if_not_allowed()
    {
        $identity = $this->makeIdentity();

        $this->stopUserCreation();

        $this->expectException(NoUser::class);
        Butler::register('test', $identity);

        $this->dontSeeInDatabase('users', ['email' => 'keoghan@klever.co.uk']);
    }

    public function test_it_can_confirm_a_social_identity_by_token()
    {
        $user = $this->makeUser();
        $identity = $this->makeIdentity();
        $socialIdentity = SocialIdentity::createFromOauthIdentity('test', $user, $identity);

        Butler::confirmIdentityByToken($socialIdentity->confirm_token);

        $this->assertNotNull($socialIdentity->fresh()->confirmed_at);
    }

    public function test_it_will_not_reregister_an_identity_that_belongs_to_another_user()
    {
        $keoghan = $this->makeUser();
        $identity = $this->makeIdentity();
        SocialIdentity::createFromOauthIdentity('test', $keoghan, $identity);

        $roger = User::create(['name' => 'Roger', 'email' => 'roger@klever.co.uk']);

        $this->actingAs($roger);
        $this->expectException(SocialIdentityAlreadyAssociated::class);

        Butler::register('test', $identity);

        $this->dontSeeInDatabase('social_identities', ['user_id' => 2]);
    }

    public function test_it_will_not_authenticate_a_new_user_if_another_is_logged_in()
    {
        $user = $this->makeUser();
        $identity = $this->makeIdentity();
        $this->makeConfirmedSocialIdentity('test', $user, $identity);

        $roger = User::create(['name' => 'Roger', 'email' => 'roger@klever.co.uk']);

        $this->actingAs($roger);

        $this->assertFalse(Butler::authenticate('test', $identity));
        $this->assertEquals(auth()->user()->id, $roger->id);
    }
}
