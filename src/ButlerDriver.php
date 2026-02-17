<?php

namespace Konsulting\Butler;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Konsulting\Butler\Contracts\RefreshableProvider;
use Konsulting\Butler\Exceptions\CouldNotRefreshToken;
use Konsulting\Butler\Exceptions\UnrefreshableProvider;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * @method \Laravel\Socialite\Two\User userFromToken(string $token) Get a Social User instance from a known access token.
 * @method array getAccessTokenResponse(string $code) Get the access token response for the given code.
 * @method $this scopes(array|string $scopes) Merge the scopes of the requested access.
 * @method $this setScopes(array|string $scopes) Set the scopes of the requested access.
 * @method array getScopes() Get the current scopes.
 * @method $this redirectUrl(string $url) Set the redirect url.
 * @method $this setHttpClient(\GuzzleHttp\Client $client) Set the Guzzle HTTP client instance.
 * @method $this setRequest(\Illuminate\Http\Request $request) Set the request instance.
 * @method $this stateless() Indicates that the provider should operate as stateless.
 * @method $this with(array $parameters) Set the custom parameters of the request.
 */
class ButlerDriver implements Provider
{
    /**
     * ButlerDriver constructor.
     *
     * @param  Provider  $socialiteProvider
     */
    protected $overrideReturn = [
        'scopes',
        'setScopes',
        'redirectUrl',
        'setHttpClient',
        'setRequest',
        'stateless',
        'with',
    ];

    public function __construct(
        /**
         * The Socialite provider instance.
         */
        private readonly Provider $socialiteProvider
    ) {}

    /**
     * Get the original Socialite Provider instance.
     *
     * @return AbstractProvider
     */
    public function getSocialiteProvider()
    {
        return $this->socialiteProvider;
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
     * Proxy calls to the Socialite Provider, if we can.
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (! method_exists($this->socialiteProvider, $method)) {
            throw new \BadMethodCallException("Unable to proxy '{$method}' call to Socialite Provider from ButlerDriver");
        }

        $result = $this->socialiteProvider->$method(...$parameters);

        // Return Butler driver instead of Socialite Provider where needed
        return in_array($method, $this->overrideReturn, true)
            ? $this
            : $result;
    }

    /**
     * @return SocialIdentity
     *
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
            'access_token' => $response['access_token'],
            'refresh_token' => Arr::get($response, 'refresh_token'),
            'expires_at' => array_key_exists('expires_in', $response)
                ? Carbon::now()->addSeconds($response['expires_in'])
                : null,
        ]);

        return $socialIdentity;
    }

    /**
     * Check that the response is an array and contains an access token.
     *
     * @param  array  $response
     *
     * @throws CouldNotRefreshToken
     */
    protected function validateRefreshResponse($response)
    {
        if (! is_array($response) || ! array_key_exists('access_token', $response)) {
            throw new CouldNotRefreshToken('Bad response received: '.serialize($response));
        }
    }
}
