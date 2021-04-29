<?php

namespace Konsulting\Butler\Controllers;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Konsulting\Butler\Butler;
use Konsulting\Butler\Exceptions\NoUser;
use Konsulting\Butler\Exceptions\SocialIdentityAlreadyAssociated;
use Konsulting\Butler\Exceptions\SocialIdentityAssociatedToLoggedInUser;
use Konsulting\Butler\Exceptions\UnableToConfirm;
use Konsulting\Butler\Exceptions\UnknownProvider;
use Konsulting\Butler\Exceptions\UserAlreadyHasSocialIdentity;
use Laravel\Socialite\Two\InvalidStateException;

class AuthController extends BaseController
{
    /**
     * Redirect the user to the appropriate providers' authentication page, to begin authentication.
     *
     * @param $provider
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect(Butler $butler, $provider)
    {
        try {
            $butler->checkProvider($provider);
        } catch (UnknownProvider $e) {
            return redirect()->route($butler->routeName('login'))
                ->with('status.content', 'Unknown Provider.')
                ->with('status.type', 'warning');
        }

        $scopes = $butler->provider($provider)->scopes ?? [];

        return empty($scopes)
            ? $butler->driver($provider)->redirect()
            : $butler->driver($provider)->scopes($scopes)->redirect();
    }

    /**
     * Obtain the user information from the provider, store details, and log in if appropriate.
     *
     * @param Butler $butler
     * @param        $provider
     *
     * @return RedirectResponse
     * @throws UnableToConfirm
     * @throws UnknownProvider
     */
    public function callback(Butler $butler, $provider)
    {
        try {
            $oauthId = $butler->driver($provider)->user();
        } catch (InvalidStateException $e) {
            return redirect()->route($butler->routeName('login'))
                ->with('status.content', 'There was a problem logging in with ' . $butler->provider($provider)->name . ', please try again.')
                ->with('status.type', 'warning');
        } catch (ClientException $e) {
            // There was a problem getting the user. Socialite does not distinguish the reason.
            // The most likely reason is that someone denied the link-up to our site. So
            // we will return to login with an appropriate message for that scenario.

            return redirect()->route($butler->routeName('login'))
                ->with('status.content', 'You have cancelled the login with ' . $butler->provider($provider)->name . '.')
                ->with('status.type', 'warning');
        }

        if ($butler->authenticate($provider, $oauthId)) {
            return redirect()->route($butler->routeName('authenticated'));
        }

        try {
            $socialIdentity = $butler->register($provider, $oauthId);

            // If the user was just created, and we have opted to allow them to
            // login without confirming the social identify, we'll log them
            // in. Otherwise, we'll ask to confirm the social identity.

            if ($butler->createdUser($socialIdentity->user)
                && config('butler.confirm_identity_for_new_user', true) == false
            ) {
                $socialIdentity->confirm();
                $this->guard()->login($socialIdentity->user);
                $message = 'Identity Saved';
            } else {
                $socialIdentity->askUserToConfirm();
                $message = 'Identity saved, please check your email to confirm.';
            }

            return redirect()->route($this->loginOrProfile($butler))
                ->with('status.content', $message)
                ->with('status.type', 'success');
        } catch (NoUser $e) {
            return redirect()->route($butler->routeName('login'))
                ->with('status.content', $e->getMessage())
                ->with('status.type', 'danger');
        } catch (SocialIdentityAlreadyAssociated $e) {
            return redirect()->route($butler->routeName('login'))
                ->with('status.content', $e->getMessage())
                ->with('status.type', 'danger');
        } catch (UserAlreadyHasSocialIdentity $e) {
            return redirect()->route($butler->routeName('profile'));
        } catch (SocialIdentityAssociatedToLoggedInUser $e) {
            return redirect()->route($butler->routeName('profile'))
                ->with('status.content', $e->getMessage())
                ->with('status.type', 'danger');
        }
    }

    /**
     * Confirm a Social Identity by matching the unique code.
     *
     * @param $token
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function confirm(Butler $butler, $token)
    {
        try {
            $socialIdentity = $butler->confirmIdentityByToken($token);

            if (config('butler.login_immediately_after_confirm', false)) {
                $this->guard()->login($socialIdentity->user);
            }
        } catch (UnableToConfirm $e) {
            return redirect()->route($butler->routeName('login'))
                ->with('status.content', 'Unable to confirm identity usage.')
                ->with('status.type', 'danger');
        }

        return redirect()->route($this->loginOrProfile($butler))
            ->with('status.content', 'Identity confirmed.')
            ->with('status.type', 'success');
    }

    protected function loginOrProfile(Butler $butler)
    {
        return $butler->routeName($this->guard()->check() ? 'profile' : 'login');
    }

    protected function guard()
    {
        return auth()->guard();
    }
}
