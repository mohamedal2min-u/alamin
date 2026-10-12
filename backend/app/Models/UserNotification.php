<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['farm_id', 'user_id', 'actor_id', 'kind', 'title', 'body'];
}
