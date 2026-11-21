<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Author extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['roles' => 'array', 'corresponding' => 'boolean'];

    public const CREDIT = [
        'Conceptualization', 'Data curation', 'Formal analysis', 'Funding acquisition', 'Investigation',
        'Methodology', 'Project administration', 'Resources', 'Software', 'Supervision', 'Validation',
        'Visualization', 'Writing – original draft', 'Writing – review & editing',
    ];

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }
}
