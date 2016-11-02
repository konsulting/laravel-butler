<?php

namespace Konsulting\Butler\Fake;

use Laravel\Socialite\Contracts\User as SocialiteUser;

class Identity implements SocialiteUser
{
    protected $values;
    public $token = 'abc';
    public $refreshToken = null;
    public $expiresIn = 60;

    public function __construct($values)
    {
        $this->values = $values;
    }

    public function getId()
    {
        return $this->values['id'];
    }

    public function getNickname()
    {
    }

    public function getName()
    {
    }

    public function getEmail()
    {
        return $this->values['email'];
    }

    public function getAvatar()
    {
    }
}
