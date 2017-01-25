<?php

namespace Konsulting\Butler;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Konsulting\Butler\Exceptions\UnableToConfirm;
use Laravel\Socialite\Contracts\User as Identity;

class SocialIdentity extends Model
{
    protected $guarded = ['id'];

    protected $dates = ['expires_at', 'confirmed_at', 'confirm_until'];

    protected $visible = ['id', 'user_id', 'provider', 'confirmed_at'];

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
            'provider' => $provider,
            'user_id' => $user->id,
            'reference' => $identity->getId(),
            'access_token' => $identity->token,
            'expires_at' => Carbon::now()->addSeconds($identity->expiresIn),
            'refresh_token' => $identity->refreshToken,
            'confirm_token' => Str::random(60),
            'confirm_until' => Carbon::now()->addMinutes(30),
        ]);
    }

    /**
     * Check if we are byond the confirmation deadline
     */
    public function pastConfirmationDeadline()
    {
        return $this->confirm_until->lt(Carbon::now());
    }

    /**
     * Confirm a SocialIdentity after locating it by it's token.
     *
     * @param $token
     *
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
            'access_token' => $identity->token,
            'expires_at' => Carbon::now()->addSeconds($identity->expiresIn),
            'refresh_token' => $identity->refreshToken,
        ]);
    }

    /**
     * Locate a confirmed SocialIdentity based on the info in an Oauth Identity.
     *
     * @param                                   $provider
     * @param \Laravel\Socialite\Contracts\User $identity
     *
     * @return mixed
     */
    public static function retrieveByOauthIdentity($provider, Identity $identity)
    {
        return static::where('provider', $provider)
            ->where('reference', $identity->getId())
            ->where('confirmed_at', '<=', Carbon::now())
            ->first();
    }
}
