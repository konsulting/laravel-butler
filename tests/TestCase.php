<?php

namespace Konsulting\Butler;

use Route;
use Schema;
use Butler;
use Konsulting\Butler\Fake\User;
use Konsulting\Butler\Fake\Identity;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Set up ServiceProviders
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [ButlerServiceProvider::class];
    }

    /**
     * Set up Facades
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return ['Butler' => ButlerFacade::class];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('butler.providers', ['test' => ['name' => 'Test']]);
        $app['config']->set('butler.user_class', User::class);

        $app['config']->set('auth.providers.users.model', User::class);

        Route::group([
            'middleware' => 'web',
        ], function ($router) {
            Route::get('/login', function () { return ''; })->name('login');
            Route::get('/home', function () { return ''; })->name('home');
            Route::get('/profile', function () { return ''; })->name('profile');
            Butler::routes();
        });
    }

    /**
     * Setup the test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->createUsersTable();

        $this->loadMigrationsFrom([
            '--database' => 'testbench',
            '--realpath' => realpath(__DIR__ . '/../migrations'),
        ]);
    }

    public function createUsersTable()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    protected function makeUser()
    {
        return User::create(['name' => 'Keoghan', 'email' => 'keoghan@klever.co.uk']);
    }

    protected function makeIdentity()
    {
        return new Identity(['id' => 1, 'name' => 'Keoghan', 'email' => 'keoghan@klever.co.uk']);
    }

    protected function makeConfirmedSocialIdentity($provider, $user, $identity)
    {
        $socialIdentity = SocialIdentity::createFromOauthIdentity($provider, $user, $identity);
        $socialIdentity->confirm();
    }

    protected function allowUserCreation()
    {
        $userProvider = app('butler_user_provider');
        $userProvider->canCreateUsers(true);
    }

    protected function stopUserCreation()
    {
        $userProvider = app('butler_user_provider');
        $userProvider->canCreateUsers(false);
    }
}
