<?php

namespace Konsulting\Butler;

use Laravel\Socialite\Contracts\User as Identity;

class EloquentUserProvider
{
    protected $canCreateUsers = false;

    protected $createdUsers = [];

    /**
     * EloquentUserProvider constructor.
     */
    public function __construct(protected $model) {}

    /**
     * Set whether the provider is allowed to create users or not.
     *
     * @param  bool  $flag
     * @return $this
     */
    public function canCreateUsers($flag = true)
    {
        $this->canCreateUsers = (bool) $flag;

        return $this;
    }

    /**
     * Obtain a user model through matching up relevant details with
     * the Identity provided. In most cases this will be matching
     * by email address, as there's not much else to go on...
     *
     * @return mixed
     */
    public function retrieveByOauthIdentity(Identity $oauthId)
    {
        return call_user_func([$this->model, 'whereEmail'], $oauthId->getEmail())
            ->first();
    }

    /**
     * Create a new user from the provided Identity. Record its creation within the instance.
     *
     * @return mixed|null
     */
    public function createFromOauthIdentity(Identity $oauthId)
    {
        if (! $this->canCreateUsers) {
            return null;
        }

        $user = call_user_func([$this->model, 'create'], [
            'name' => $oauthId->getName(),
            'email' => $oauthId->getEmail(),
        ]);

        $this->createdUsers[] = $user->id;

        return $user;
    }

    /**
     * Confirm or deny whether we created a user.
     *
     * @return bool
     */
    public function createdUser($user)
    {
        return in_array($user->id, $this->createdUsers);
    }
}
