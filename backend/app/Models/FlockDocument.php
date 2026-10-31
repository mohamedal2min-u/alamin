<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlockDocument extends Model
{
    protected $fillable = ['farm_id', 'flock_id', 'record_type', 'record_id', 'title', 'path', 'created_by'];
}
