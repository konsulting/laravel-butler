<?php

namespace Konsulting\Butler\Controllers;

use Butler;
use GuzzleHttp\Exception\ClientException;
use Konsulting\Butler\Exceptions\NoUser;
use Konsulting\Butler\Exceptions\UnknownProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Konsulting\Butler\Exceptions\UnableToConfirm;
use Illuminate\Routing\Controller as BaseController;
use Laravel\Socialite\Contracts\Factory as SocialiteManager;
use Konsulting\Butler\Exceptions\UserAlreadyHasSocialIdentity;
use Konsulting\Butler\Exceptions\SocialIdentityAlreadyAssociated;

class AuthController extends BaseController
{
    protected $socialite;

    /**
     * Create a new controller instance.
     *
     * @param \Laravel\Socialite\Contracts\Factory $socialite
     */
    public function __construct(SocialiteManager $socialite)
    {
        $this->socialite = $socialite;
    }

    /**
     * Redirect the user to the appropriate providers' authentication page, to begin authentication.
     *
     * @param $provider
     *
     * @return Response
     */
    public function redirect($provider)
    {
        try {
            Butler::checkProvider($provider);
        } catch (UnknownProvider $e) {
            return redirect()->route(Butler::routeName('login'))
                ->with('status.content', 'Unknown Provider.')
                ->with('status.type', 'warning');
        }
        return $this->socialite->driver($provider)->redirect();
    }

    /**
     * Obtain the user information from the provider, store details, and log in if appropriate.
     *
     * @param $provider
     *
     * @return Response
     */
    public function callback($provider)
    {
        try {
            $oauthId = $this->socialite->driver($provider)->user();
        } catch (InvalidStateException $e) {
            return redirect()->route(Butler::routeName('login'))
                ->with('status.content', 'There was a problem logging in with ' . Butler::provider($provider)->name . ', please try again.')
                ->with('status.type', 'warning');
        } catch (ClientException $e) {
            // There was a problem getting the user. Socialite does not distinguish the reason.
            // The most likely reason is that someone denied the link-up to our site. So
            // we will return to login with an appropriate message for that scenario.

            return redirect()->route(Butler::routeName('login'))
                ->with('status.content', 'You have cancelled the login with ' . Butler::provider($provider)->name . '.')
                ->with('status.type', 'warning');
        }

        if (Butler::authenticate($provider, $oauthId)) {
            return redirect()->route(Butler::routeName('authenticated'));
        }

        try {
            $socialIdentity = Butler::register($provider, $oauthId);

            // If the user was just created, and we have opted to allow them to
            // login without confirming the social identify, we'll log them
            // in. Otherwise, we'll ask to confirm the social identity.

            if (Butler::createdUser($socialIdentity->user)
                && config('butler.confirm_identity_for_new_user', true) == false
            ) {
                $socialIdentity->confirm();
                $this->guard()->login($socialIdentity->user);
                $message = 'Identity Saved';
            } else {
                $socialIdentity->askUserToConfirm();
                $message = 'Identity saved, please check your email to confirm.';
            }

            return redirect()->route($this->loginOrProfile())
                ->with('status.content', $message)
                ->with('status.type', 'success');
        } catch (NoUser $e) {
            return redirect()->route(Butler::routeName('login'))
                ->with('status.content', $e->getMessage())
                ->with('status.type', 'danger');
        } catch (SocialIdentityAlreadyAssociated $e) {
            return redirect()->route(Butler::routeName('login'))
                ->with('status.content', $e->getMessage())
                ->with('status.type', 'danger');
        } catch (UserAlreadyHasSocialIdentity $e) {
            return redirect()->route(Butler::routeName('profile'));
        }
    }

    /**
     * Confirm a Social Identity by matching the unique code
     *
     * @param $token
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function confirm($token)
    {
        try {
            $socialIdentity = Butler::confirmIdentityByToken($token);

            if (config('butler.login_immediately_after_confirm', false)) {
                $this->guard()->login($socialIdentity->user);
            }

        } catch (UnableToConfirm $e) {
            return redirect()->route(Butler::routeName('login'))
                ->with('status.content', 'Unable to confirm identity usage.')
                ->with('status.type', 'danger');
        }

        return redirect()->route($this->loginOrProfile())
            ->with('status.content', 'Identity confirmed.')
            ->with('status.type', 'success');
    }

    protected function loginOrProfile()
    {
        return Butler::routeName($this->guard()->check() ? 'profile' : 'login');
    }

    protected function guard()
    {
        return auth()->guard();
    }
}
