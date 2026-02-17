<?php

namespace Konsulting\Butler;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Konsulting\Butler\Contracts\RefreshableProvider;
use Konsulting\Butler\Exceptions\ButlerException;
use Konsulting\Butler\Exceptions\SocialIdentityNotFound;
use Konsulting\Butler\Jobs\ButlerJob;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\SocialiteServiceProvider;
use Mockery;
use Mockery\Mock;

class ButlerJobTest extends DatabaseTestCase
{
    /** @var Authenticatable|Mock */
    protected $user;

    /** @var SocialIdentity */
    protected $socialIdentity;

    /** @var SocialiteProvider|Mock */
    protected $socialiteProvider;

    protected $mockSocialiteManager = false;

    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [SocialiteServiceProvider::class]);
    }

    public function setUp(): void
    {
        parent::setUp();

        $socialite = app(Factory::class);
        $this->socialiteProvider = Mockery::mock(SocialiteProvider::class, RefreshableProvider::class);

        $socialite->extend('my-service', function () {
            return $this->socialiteProvider;
        });

        $this->user = Mockery::mock(Authenticatable::class);
        $this->user->shouldReceive('getAuthIdentifier')->andReturn(1);

        $this->socialIdentity = SocialIdentity::create([
            'access_token' => 'the access token',
            'expires_at' => Carbon::now()->addDay(),
            'refresh_token' => 'the refresh token',
            'user_id' => $this->user->getAuthIdentifier(),
            'provider' => 'my-service',
        ]);
    }

    /** @test */
    public function it_executes_a_job_with_a_valid_access_token()
    {
        $task = Mockery::mock();

        $task->shouldReceive('run')->with($this->socialIdentity->access_token)->andReturn(true)->once();
        MyButlerJob::dispatch($this->user, $task);
    }

    /** @test */
    public function it_refreshes_an_expired_token_before_running_the_task_and_invalidates_the_token()
    {
        $this->socialIdentity->update(['expires_at' => Carbon::now()->subDay()]);
        $task = Mockery::mock();

        // Refresh before loop
        $this->socialiteProvider->shouldReceive('getRefreshResponse')->with('the refresh token')
            ->andReturn([
                'access_token' => 'new token 1',
                'refresh_token' => 'new refresh 1',
            ])->once()->ordered();

        // First run, fail
        $task->shouldReceive('run')->with('new token 1')->andReturn(false)->once()->ordered();

        MyButlerJob::dispatch($this->user, $task);

        $this->socialIdentity->refresh();
        $this->assertNull($this->socialIdentity->access_token);
        $this->assertNull($this->socialIdentity->expires_at);
    }

    /** @test */
    public function it_throws_an_exception_if_the_social_identity_is_not_found()
    {
        $this->socialIdentity->delete();

        $this->expectException(SocialIdentityNotFound::class);
        MyButlerJob::dispatch($this->user, Mockery::mock());
    }

    /** @test */
    public function it_handles_thrown_exceptions()
    {
        $exception = new TestException;

        $task = Mockery::mock();
        $task->shouldReceive('run')->andThrow($exception)->once();
        $task->shouldReceive('handleException')->with($exception)->once();

        MyButlerJob::dispatch($this->user, $task);
    }
}

class MyButlerJob extends ButlerJob
{
    private $task;

    public function __construct(Authenticatable $user, $task)
    {
        parent::__construct($user);
        $this->task = $task;
    }

    protected function getSocialProviderName()
    {
        return 'my-service';
    }

    protected function doAction($token)
    {
        // Pass the token to our 'task' so we can assert against it
        if (! $this->task->run($token)) {
            $this->invalidateAccessToken();
        }
    }

    protected function handleException(\Exception $e)
    {
        if ($e instanceof TestException) {
            return $this->task->handleException($e);
        }

        return parent::handleException($e);
    }
}

class TestException extends ButlerException
{
}
