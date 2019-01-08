<?php

namespace Konsulting\Butler;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\AbstractProvider;

class ButlerDriver
{
    /**
     * The Socialite provider instance, used for performing actions.
     *
     * @var AbstractProvider
     */
    private $socialiteProvider;

    public function __construct(Provider $socialiteProvider)
    {
        $this->socialiteProvider = $socialiteProvider;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect()
    {
        return $this->socialiteProvider->redirect();
    }
}
