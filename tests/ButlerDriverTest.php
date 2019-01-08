<?php

namespace Konsulting\Butler;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\SocialiteManager;
use Mockery;
use Mockery\Mock;

class ButlerDriverTest extends DatabaseTestCase
{
    /** @var Mock|SocialiteManager */
    protected $socialite;

    /** @var Mock|Provider */
    protected $socialiteProvider;

    /** @var Butler */
    protected $butler;

    public function setUp()
    {
        parent::setUp();

        $this->socialite = Mockery::mock(SocialiteManager::class);
        $this->socialiteProvider = Mockery::mock(Provider::class);
        $this->butler = new Butler($this->socialite, [
            'my-service' => ['name' => 'My Service'],
        ], []);
    }

    /** @test */
    public function it_performs_a_redirect_on_the_socialite_driver()
    {
        $redirect = Mockery::mock(RedirectResponse::class);
        $this->socialiteShouldReturnDriver();
        $this->socialiteProvider->shouldReceive('redirect')->withNoArgs()
            ->andReturn($redirect)->once();

        $this->assertSame($redirect, $this->butler->driver('my-service')->redirect());
    }

    /** @test */
    public function it_gets_the_user()
    {
        $user = Mockery::mock(SocialiteUser::class);
        $this->socialiteShouldReturnDriver();
        $this->socialiteProvider->shouldReceive('user')->withNoArgs()
            ->andReturn($user)->once();

        $this->assertSame($user, $this->butler->driver('my-service')->user());
    }

    /**
     * Set the expectation and return value on the Socialite mock.
     *
     * @return void
     */
    protected function socialiteShouldReturnDriver()
    {
        $this->socialite->shouldReceive('driver')->with('my-service')
            ->andReturn($this->socialiteProvider)->once();
    }
}
