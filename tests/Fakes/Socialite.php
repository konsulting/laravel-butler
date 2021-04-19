<?php

namespace Konsulting\Butler\Fake;

use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\SocialiteManager;

class Socialite extends SocialiteManager implements Factory
{
    protected $driver;

    public function driver($driver = null)
    {
        $this->driver = $driver;

        return $this;
    }

    public function redirect()
    {
        return 'redirect to location for ' . $this->driver;
    }

    public function user()
    {
        return new Identity(['id' => 1, 'name' => 'Keoghan', 'email' => 'keoghan@klever.co.uk']);
    }
}
