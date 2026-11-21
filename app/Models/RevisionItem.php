<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevisionItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['done' => 'boolean'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
