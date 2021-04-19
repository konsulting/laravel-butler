<?php

namespace Konsulting\Butler;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Konsulting\Butler\Contracts\RefreshableProvider;
use Konsulting\Butler\Exceptions\CouldNotRefreshToken;
use Konsulting\Butler\Exceptions\UnrefreshableProvider;
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

    /**
     * @param SocialIdentity $socialIdentity
     * @return SocialIdentity
     * @throws UnrefreshableProvider
     * @throws CouldNotRefreshToken
     */
    public function refresh(SocialIdentity $socialIdentity)
    {
        if (! $this->socialiteProvider instanceof RefreshableProvider) {
            throw new UnrefreshableProvider($this->socialiteProvider);
        }

        $response = $this->socialiteProvider->getRefreshResponse($socialIdentity->refresh_token);

        $this->validateRefreshResponse($response);

        $socialIdentity->update([
            'access_token'  => $response['access_token'],
            'refresh_token' => Arr::get($response, 'refresh_token'),
            'expires_at'    => array_key_exists('expires_in', $response)
                ? Carbon::now()->addSeconds($response['expires_in'])
                : null,
        ]);

        return $socialIdentity;
    }

    /**
     * Check that the response is an array and contains an access token.
     *
     * @param array $response
     * @throws CouldNotRefreshToken
     */
    protected function validateRefreshResponse($response)
    {
        if (! is_array($response) || ! array_key_exists('access_token', $response)) {
            throw new CouldNotRefreshToken('Bad response received: ' . serialize($response));
        }
    }
}
