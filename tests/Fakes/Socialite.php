<?php

namespace Konsulting\Butler\Fake;

use Laravel\Socialite\SocialiteManager;

class Socialite extends SocialiteManager
{
    public function driver($driver = null)
    {
        return new Provider($driver);
    }
}
