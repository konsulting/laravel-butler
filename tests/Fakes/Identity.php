<?php

namespace Konsulting\Butler\Fake;

use Laravel\Socialite\Contracts\User as SocialiteUser;

class Identity implements SocialiteUser
{
    public $token = 'abc';

    public $refreshToken = null;

    public $expiresIn = 60;

    public function __construct(protected $values) {}

    public function getId()
    {
        return $this->values['id'];
    }

    public function getNickname() {}

    public function getName() {}

    public function getEmail()
    {
        return $this->values['email'];
    }

    public function getAvatar() {}
}
