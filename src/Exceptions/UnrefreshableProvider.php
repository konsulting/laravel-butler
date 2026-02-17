<?php

namespace Konsulting\Butler\Exceptions;

use Konsulting\Butler\Contracts\RefreshableProvider;
use Throwable;

class UnrefreshableProvider extends ButlerException
{
    public function __construct($provider, $code = 0, ?Throwable $previous = null)
    {
        $message = 'Cannot refresh because '.$provider::class.' does not implement '.RefreshableProvider::class;

        parent::__construct($message, $code, $previous);
    }
}
