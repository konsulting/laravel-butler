<?php


namespace Konsulting\Butler\Fake;


class Provider implements \Laravel\Socialite\Contracts\Provider
{
    protected $driver;
    protected $scopes = [];

    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    public function redirect()
    {
        return 'redirect to location for ' . $this->driver . (empty($this->scopes) ? '' : ', with '.implode(', ', $this->scopes));
    }

    public function user()
    {
        return new Identity(['id' => 1, 'name' => 'Keoghan', 'email' => 'keoghan@klever.co.uk']);
    }

    public function scopes($scopes)
    {
        $this->scopes = array_unique(array_merge($this->scopes, (array) $scopes));

        return $this;
    }
}