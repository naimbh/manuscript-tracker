<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deadline extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $casts = ['due_on' => 'date', 'done' => 'boolean'];

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }

    public function daysLeft(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->due_on, false);
    }
}
