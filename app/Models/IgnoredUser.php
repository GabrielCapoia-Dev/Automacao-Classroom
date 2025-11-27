<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IgnoredUser extends Model
{
    protected $fillable = ['admin_id', 'user_id'];
}
