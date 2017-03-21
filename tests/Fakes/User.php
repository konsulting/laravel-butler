<?php

namespace Konsulting\Butler\Fake;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable, Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}
