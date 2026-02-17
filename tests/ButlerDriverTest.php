<?php

namespace Konsulting\Butler;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Konsulting\Butler\Contracts\RefreshableProvider;
use Konsulting\Butler\Exceptions\CouldNotRefreshToken;
use Konsulting\Butler\Exceptions\UnrefreshableProvider;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\SocialiteManager;
use Mockery;
use Mockery\Mock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ButlerDriverTest extends DatabaseTestCase
{
    /** @var Mock|SocialiteManager */
    protected $socialite;

    /** @var Mock|Provider */
    protected $socialiteProvider;

    /** @var Butler */
    protected $butler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->socialite = Mockery::mock(SocialiteManager::class);
        $this->socialiteProvider = Mockery::mock(Provider::class);

        $this->butler = new Butler($this->socialite, [
            'my-service' => ['name' => 'My Service'],
        ], []);
    }

    #[Test]
    public function it_performs_a_redirect_on_the_socialite_driver()
    {
        $this->socialiteShouldReturnMockedProvider();

        $redirect = Mockery::mock(RedirectResponse::class);
        $this->socialiteProvider->shouldReceive('redirect')->withNoArgs()
            ->andReturn($redirect)->once();

        $this->assertSame($redirect, $this->butler->driver('my-service')->redirect());
    }

    #[Test]
    public function it_gets_the_user()
    {
        $this->socialiteShouldReturnMockedProvider();

        $user = Mockery::mock(SocialiteUser::class);
        $this->socialiteProvider->shouldReceive('user')->withNoArgs()
            ->andReturn($user)->once();

        $this->assertSame($user, $this->butler->driver('my-service')->user());
    }

    #[Test]
    public function it_cannot_refresh_a_non_refreshable_provider()
    {
        $this->socialiteShouldReturnMockedProvider();

        $this->expectException(UnrefreshableProvider::class);

        $this->butler->driver('my-service')->refresh(Mockery::mock(SocialIdentity::class));
    }

    #[Test]
    public function it_refreshes_a_token()
    {
        $this->socialiteProvider = Mockery::mock(Provider::class, RefreshableProvider::class);
        $this->socialiteShouldReturnMockedProvider();

        $socialIdentity = SocialIdentity::factory()->create();
        $this->socialiteProvider->shouldReceive('getRefreshResponse')->with($socialIdentity->refresh_token)
            ->once()->andReturn([
                'access_token' => 'new access token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'refresh_token' => 'new refresh token',
            ]);

        $this->butler->driver('my-service')->refresh($socialIdentity);

        $socialIdentity->refresh();
        $this->assertSame('new access token', $socialIdentity->access_token);
        $this->assertSame('new refresh token', $socialIdentity->refresh_token);
        $this->assertSame(Carbon::now()->addSeconds(3600)->toAtomString(), $socialIdentity->expires_at->toAtomString());
    }

    #[Test]
    public function it_refreshes_a_token_and_does_not_receive_an_expiry_time_or_refresh_token()
    {
        $this->socialiteProvider = Mockery::mock(Provider::class, RefreshableProvider::class);
        $this->socialiteShouldReturnMockedProvider();

        $socialIdentity = SocialIdentity::factory()->create([
            'refresh_token' => 'my refresh token',
            'expires_at' => Carbon::now(),
        ]);
        $this->socialiteProvider->shouldReceive('getRefreshResponse')->once()->andReturn(['access_token' => 'a']);

        $this->butler->driver('my-service')->refresh($socialIdentity);

        $this->assertSame(null, $socialIdentity->expires_at);
        $this->assertSame(null, $socialIdentity->refresh_token);
    }

    #[Test]
    #[DataProvider('badRefreshTokenResponseProvider')]
    public function it_fails_a_refresh_if_the_response_is_not_valid($refreshResponse)
    {
        $this->socialiteProvider = Mockery::mock(Provider::class, RefreshableProvider::class);
        $this->socialiteShouldReturnMockedProvider();

        $socialIdentity = SocialIdentity::factory()->create();
        $this->socialiteProvider->shouldReceive('getRefreshResponse')->once()->andReturn($refreshResponse);

        $this->expectException(CouldNotRefreshToken::class);

        $this->butler->driver('my-service')->refresh($socialIdentity);
    }

    public static function badRefreshTokenResponseProvider()
    {
        return [
            [['no access token' => 'a']],
            [false],
        ];
    }

    /**
     * Set the expectation and return value on the Socialite mock.
     *
     * @return void
     */
    protected function socialiteShouldReturnMockedProvider()
    {
        $this->socialite->shouldReceive('driver')->with('my-service')
            ->andReturn($this->socialiteProvider)->once();
    }
}
