<?php

namespace Konsulting\Butler;

use Konsulting\Butler\Exceptions\NoUser;
use Konsulting\Butler\Exceptions\UnknownProvider;
use Laravel\Socialite\Contracts\User as Identity;
use Konsulting\Butler\Exceptions\SocialIdentityAlreadyAssociated;

class Butler
{
    protected $providers;
    protected $routeNames;

    public function __construct($providers, $routeNames)
    {
        $this->providers = $this->prepareProviders($providers);
        $this->routeNames = collect($routeNames);
    }

    /**
     * Prepare the incoming providers array into a collection of simple objects.
     *
     * @param $providers
     *
     * @return static
     */
    protected function prepareProviders($providers)
    {
        return collect($providers)->map(function ($provider) {
            return (object) $provider;
        });
    }

    /**
     * Check that the provider is one that is supported by the app.
     *
     * @param $name
     *
     * @throws \Konsulting\Butler\Exceptions\UnknownProvider
     */
    public function checkProvider($name)
    {
        if (! $this->providers->keys()->contains($name)) {
            throw new UnknownProvider($name);
        }
    }

    /**
     * Simple method to access the available provider collection.
     *
     * E.g. when presenting a list of buttons in a view
     *
     * @return \Konsulting\Butler\Butler
     */
    public function providers()
    {
        return $this->providers;
    }

    /**
     * Return a single provider's details
     *
     * @param $provider
     *
     * @return stdClass
     */
    public function provider($provider)
    {
        $this->checkProvider($provider);

        return $this->providers[$provider];
    }

    /**
     * Simple function to include the routes where needed.
     */
    public function routes()
    {
        include __DIR__ . '/../routes/web.php';
    }

    /**
     * Get the configured route name for the application
     * by the Butler key. It means we can configure it
     * to fit in with the application more easily.
     *
     * @param $key
     *
     * @return mixed
     */
    public function routeName($key)
    {
        return $this->routeNames[$key];
    }

    /**
     * Authenticate a Socialite User (Identity) and update the token
     * information for a given provider --- only if appropriate
     * We also check whether the provider is set up for use.
     *
     * @param                                   $provider
     * @param \Laravel\Socialite\Contracts\User $identity
     *
     * @return bool
     */
    public function authenticate($provider, Identity $identity)
    {
        $this->checkProvider($provider);

        // Don't authenticate if already logged in
        if ($this->guard()->check()) {
            return true;
        }

        $socialIdentity = SocialIdentity::retrieveByOauthIdentity($provider, $identity);

        if (! $socialIdentity) {
            return false;
        }

        $socialIdentity->updateFromOauthIdentity($identity);

        $this->guard()->login($socialIdentity->user);

        return true;
    }

    /**
     * Get the Guard instance
     *
     * @return mixed
     */
    protected function guard()
    {
        return auth()->guard();
    }

    /**
     * Register an Identity with a user. We'll use the authenticated user,
     * or if we can't find an appropriate user, create one if allowed.
     * Otherwise, we will fail through a graceful Exception :)
     *
     * @param                                   $provider
     * @param \Laravel\Socialite\Contracts\User $identity
     *
     * @return static
     * @throws \Konsulting\Butler\Exceptions\NoUser
     * @throws \Konsulting\Butler\Exceptions\SocialIdentityAlreadyAssociated
     */
    public function register($provider, Identity $identity)
    {
        $this->checkProvider($provider);

        $authenticatedUser = $this->guard()->check() ? $this->guard()->user() : null;
        $user = $this->userProvider()->retrieveByOauthIdentity($identity);

        // if the authenticated user doesn't match the one for the social identity, fail
        if ($authenticatedUser && $user && $authenticatedUser->getKey() !== $user->getKey()) {
            throw new SocialIdentityAlreadyAssociated(
                "This {$this->providers[$provider]->name} account is already associated with another user."
            );
        }

        if (! $user) {
            $user = $this->userProvider()->createFromOauthIdentity($identity);
        }

        if (! $user) {
            throw new NoUser('Could not find an existing user, or register a new one.');
        }

        return SocialIdentity::createFromOauthIdentity($provider, $user, $identity);
    }

    /**
     * Confirm a SocialIdentity by providing the token.
     *
     * @param $token
     */
    public static function confirmIdentityByToken($token)
    {
        return SocialIdentity::confirmByToken($token);
    }

    /**
     * Obtain the UserProvider Instance.
     *
     * @return \Illuminate\Foundation\Application|mixed
     */
    public function userProvider()
    {
        return app('butler_user_provider');
    }

    /**
     * Check if butler created the user.
     *
     * @param $user
     *
     * @return mixed
     */
    public function createdUser($user)
    {
        return $this->userProvider()->createdUser($user);
    }
}
