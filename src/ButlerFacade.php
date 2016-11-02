<?php

namespace Konsulting\Butler;

use Illuminate\Support\Facades\Facade;

class ButlerFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'butler';
    }
}
