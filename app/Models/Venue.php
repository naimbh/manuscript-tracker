<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $casts = [
        'deadline_on' => 'date',
        'deadline_expected' => 'boolean',
        'watch' => 'boolean',
        'checked_at' => 'datetime',
        'sources' => 'array',
    ];

    public const KINDS = ['journal' => 'Journal', 'conference' => 'Conference', 'preprint' => 'Preprint server'];

    public const REVIEW_TYPES = ['double' => 'Double-anonymous', 'single' => 'Single-anonymous', 'open' => 'Open review', 'none' => 'No peer review'];

    public const PREPRINT_POLICIES = ['allowed' => 'Preprints allowed', 'after' => 'Only after decision', 'discouraged' => 'Discouraged', 'unknown' => 'Not checked'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function deadlineDays(): ?int
    {
        return $this->deadline_on ? (int) now()->startOfDay()->diffInDays($this->deadline_on, false) : null;
    }

    public function isAnonymous(): bool
    {
        return $this->review_type === 'double';
    }
}
