<?php

namespace Konsulting\Butler;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Konsulting\Butler\Exceptions\UnableToConfirm;
use Laravel\Socialite\Contracts\User as Identity;

class SocialIdentity extends Model
{
    protected $guarded = ['id'];

    protected $dates = ['expires_at', 'confirmed_at', 'confirm_until'];

    protected $visible = ['id', 'user_id', 'provider', 'confirmed_at'];

    /**
     * {@inheritdoc}
     */
    public function getTable()
    {
        return config('butler.social_identities_table_name') ?: 'social_identities';
    }

    /**
     * A Social Identity belongs to a User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('butler.user_class'));
    }

    /**
     * Create a Social Identity, for a given provider and user using the
     * details provided through the Oauth Identity. We store all of
     * the token information to allow use of the provider API.
     *
     * @param                                   $provider
     * @param                                   $user
     * @param \Laravel\Socialite\Contracts\User $identity
     *
     * @return static
     */
    public static function createFromOauthIdentity($provider, $user, Identity $identity)
    {
        $existing = static::where('provider', $provider)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && ! $existing->pastConfirmationDeadline()) {
            return $existing;
        }

        if ($existing) {
            $existing->delete();
        }

        return static::create([
            'provider'      => $provider,
            'user_id'       => $user->id,
            'reference'     => $identity->getId(),
            'access_token'  => $identity->token,
            'expires_at'    => Carbon::now()->addSeconds($identity->expiresIn),
            'refresh_token' => $identity->refreshToken,
            'confirm_token' => Str::random(60),
            'confirm_until' => Carbon::now()->addMinutes(30),
        ]);
    }

    /**
     * Check if we are beyond the confirmation deadline. If no deadline has been set, treat it as being past the
     * deadline.
     *
     * @return bool
     */
    public function pastConfirmationDeadline()
    {
        return (! $this->confirm_until instanceof Carbon) ?: $this->confirm_until->lt(Carbon::now());
    }

    /**
     * Confirm a SocialIdentity after locating it by its token.
     *
     * @param $token
     *
     * @return static
     * @throws \Konsulting\Butler\Exceptions\UnableToConfirm
     */
    public static function confirmByToken($token)
    {
        $identity = static::where('confirm_token', $token)->first();

        if (! $identity) {
            throw new UnableToConfirm;
        }

        $identity->confirm();

        return $identity;
    }

    /**
     * Confirm a token. Reset other confirm values to minimise chances of collisions.
     */
    public function confirm()
    {
        if ($this->pastConfirmationDeadline()) {
            throw new UnableToConfirm();
        }

        $this->confirmed_at = Carbon::now();
        $this->confirm_token = '';
        $this->confirm_until = null;
        $this->save();
    }

    /**
     * Notify the related user of intention to use the Identity.
     */
    public function askUserToConfirm()
    {
        $notificationClass = config('butler.confirm_identity_notification');

        $this->user->notify(new $notificationClass($this));
    }

    /**
     * Update details based on provided Oauth Identity.
     *
     * @param \Laravel\Socialite\Contracts\User $identity
     */
    public function updateFromOauthIdentity(Identity $identity)
    {
        $this->update([
            'access_token'  => $identity->token,
            'expires_at'    => Carbon::now()->addSeconds($identity->expiresIn),
            'refresh_token' => $identity->refreshToken,
        ]);
    }

    /**
     * Locate a confirmed SocialIdentity based on the info in an Oauth Identity.
     *
     * @param                                   $provider
     * @param \Laravel\Socialite\Contracts\User $identity
     *
     * @return static|null
     */
    public static function retrieveByOauthIdentity($provider, Identity $identity)
    {
        return static::where('provider', $provider)
            ->where('reference', $identity->getId())
            ->where('confirmed_at', '<=', Carbon::now())
            ->first();
    }

    /**
     * Locate a SocialIdentity that is confirmed or awaiting
     * confirmation based on the info in an Oauth Identity.
     *
     * @param                                   $provider
     * @param \Laravel\Socialite\Contracts\User $identity
     *
     * @return static|null
     */
    public static function retrievePossibleByOauthIdentity($provider, Identity $identity)
    {
        return static::where('provider', $provider)
            ->where('reference', $identity->getId())
            ->where(function ($query) {
                $now = Carbon::now();

                $query->where('confirmed_at', '<=', $now)
                    ->orWhere('expires_at', '>=', $now);
            })
            ->first();
    }

    /**
     * Check if the access token's expiry date has passed. If the expiry date is null we'll assume that the token has
     * expired.
     *
     * @return bool
     */
    public function accessTokenIsExpired()
    {
        if ($this->expires_at instanceof Carbon) {
            return $this->expires_at->isPast();
        }

        return true;
    }

    /**
     * Remove the access token and expiry date, usually to force a token refresh after a failed request.
     *
     * @return bool
     */
    public function invalidateAccessToken()
    {
        return $this->update(['access_token' => null, 'expires_at' => null]);
    }
}
