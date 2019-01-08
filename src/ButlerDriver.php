<?php

namespace Konsulting\Butler;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\AbstractProvider;

class ButlerDriver implements Provider
{
    /**
     * The Socialite provider instance.
     *
     * @var AbstractProvider
     */
    private $socialiteProvider;

    public function __construct(Provider $socialiteProvider)
    {
        $this->socialiteProvider = $socialiteProvider;
    }

    /**
     * Redirect the user to the authentication page for the provider.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect()
    {
        return $this->socialiteProvider->redirect();
    }

    /**
     * Get the User instance for the authenticated user.
     *
     * @return \Laravel\Socialite\Contracts\User
     */
    public function user()
    {
        return $this->socialiteProvider->user();
    }
}
