<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $casts = ['finished_at' => 'datetime'];
}
