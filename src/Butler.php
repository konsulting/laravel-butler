<?php

namespace Konsulting\Butler;

use Konsulting\Butler\Exceptions\NoUser;
use Konsulting\Butler\Exceptions\SocialIdentityAlreadyAssociated;
use Konsulting\Butler\Exceptions\SocialIdentityAssociatedToLoggedInUser;
use Konsulting\Butler\Exceptions\UnknownProvider;
use Konsulting\Butler\Exceptions\UserAlreadyHasSocialIdentity;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as Identity;
use Laravel\Socialite\SocialiteManager;
use stdClass;

class Butler
{
    /**
     * The Socialite instance.
     *
     * @var SocialiteManager
     */
    protected $socialite;

    /**
     * The social provider config.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $providers;

    /**
     * The mapping between route names and URLs within the host application.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $routeNames;

    /**
     * Butler constructor.
     *
     * @param  SocialiteFactory  $socialite
     * @param  array[]  $providers
     * @param  array  $routeNames
     */
    public function __construct(SocialiteFactory $socialite, $providers, $routeNames)
    {
        $this->providers = $this->prepareProviders($providers);
        $this->routeNames = collect($routeNames);
        $this->socialite = $socialite;
    }

    /**
     * Prepare the incoming providers array into a collection of simple objects.
     *
     * @param $providers
     * @return \Illuminate\Support\Collection
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
     * @param  string  $name
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
     * @return \Illuminate\Support\Collection
     */
    public function providers()
    {
        return $this->providers;
    }

    /**
     * Return a single provider's details.
     *
     * @param $provider
     * @return stdClass
     */
    public function provider($provider)
    {
        $this->checkProvider($provider);

        return $this->providers[$provider];
    }

    /**
     * Get the Butler driver for the given Socialite provider. This wraps the underlying Socialite driver and provides
     * some extra functionality.
     *
     * @param  string  $providerName
     * @return ButlerDriver
     */
    public function driver($providerName)
    {
        return new ButlerDriver($this->socialite->driver($providerName));
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
     * @return mixed
     */
    public function routeName($key)
    {
        // Allow temporary override of routes
        if (session('butler.redirect.' . $key)) {
            return session('butler.redirect.' . $key);
        }

        return $this->routeNames[$key];
    }

    /**
     * Handle forced redirection to full urls, or routes with parameters, as well as plain routes names.
     *
     * @param $key
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectResponseTo($key)
    {
        $redirectTo = $this->routeName($key);

        if (is_array($redirectTo)) {
            return redirect()->route(...$redirectTo);
        }

        if (strpos($redirectTo, '/') === 0 || strpos($redirectTo, 'https://') === 0 || strpos($redirectTo, 'http://') === 0) {
            return redirect()->to($redirectTo);
        }

        return redirect()->route($redirectTo);
    }

    /**
     * Authenticate a Socialite User (Identity) and update the token information for a given provider --- only if
     * appropriate. We also check whether the provider is set up for use.
     *
     * @param  string  $provider  The provider name
     * @param  \Laravel\Socialite\Contracts\User  $identity
     * @return bool
     *
     * @throws UnknownProvider
     */
    public function authenticate($provider, Identity $identity)
    {
        $this->checkProvider($provider);

        if ($this->guard()->check()) {
            return false;
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
     * Get the Guard instance.
     *
     * @return mixed
     */
    protected function guard()
    {
        return auth()->guard();
    }

    /**
     * Register an Identity with a user. We'll use the authenticated user, or if we can't find an appropriate user,
     * create one if allowed. Otherwise, we will fail through a graceful Exception :).
     *
     * @param  string  $provider  The provider name
     * @param  \Laravel\Socialite\Contracts\User  $identity
     * @return SocialIdentity
     *
     * @throws NoUser
     * @throws SocialIdentityAlreadyAssociated
     * @throws UnknownProvider
     * @throws UserAlreadyHasSocialIdentity
     */
    public function register($provider, Identity $identity)
    {
        $this->checkProvider($provider);
        $this->guardExistingSocialIdentities($provider, $identity);

        if (config('butler.can_associate_to_logged_in_user', false) === true && $this->guard()->user()) {
            SocialIdentity::createFromOauthIdentity($provider, $this->guard()->user(), $identity);

            throw new SocialIdentityAssociatedToLoggedInUser('Social Identity linked to your account');
        }

        $user = $this->userProvider()->retrieveByOauthIdentity($identity);

        if (! $user) {
            $user = $this->userProvider()->createFromOauthIdentity($identity);
        }

        if (! $user) {
            throw new NoUser('Could not find an existing user, or register a new one.');
        }

        return SocialIdentity::createFromOauthIdentity($provider, $user, $identity);
    }

    protected function guardExistingSocialIdentities($provider, Identity $identity)
    {
        if (! $this->guard()->check()) {
            return;
        }

        $authenticatedUser = $this->guard()->user();
        $existingSocialIdentity = SocialIdentity::retrievePossibleByOauthIdentity($provider, $identity);

        if (! $existingSocialIdentity) {
            return;
        }

        // if the authenticated user matches the one found with the
        if ($authenticatedUser->getKey() === $existingSocialIdentity->user->getKey()) {
            throw new UserAlreadyHasSocialIdentity();
        }

        // if the authenticated user doesn't match the one that is found to match the identity details, fail
        throw new SocialIdentityAlreadyAssociated(
            "This {$this->providers[$provider]->name} account is already associated with another user."
        );
    }

    /**
     * Confirm a SocialIdentity by providing the token.
     *
     * @param $token
     * @return SocialIdentity
     *
     * @throws Exceptions\UnableToConfirm
     */
    public static function confirmIdentityByToken($token)
    {
        return SocialIdentity::confirmByToken($token);
    }

    /**
     * Obtain the UserProvider Instance.
     *
     * @return EloquentUserProvider
     */
    public function userProvider()
    {
        return app('butler_user_provider');
    }

    /**
     * Check if butler created the user.
     *
     * @param $user
     * @return mixed
     */
    public function createdUser($user)
    {
        return $this->userProvider()->createdUser($user);
    }
}
