<?php

namespace Konsulting\Butler;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Provider;
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
        $this->socialite->shouldReceive('driver')->with('my-service')
            ->andReturn($this->socialiteProvider)->once();
        $this->socialiteProvider->shouldReceive('redirect')->withNoArgs()
            ->andReturn(new RedirectResponse('/'))->once();

        $this->assertInstanceOf(RedirectResponse::class, $this->butler->driver('my-service')->redirect());
    }
}
