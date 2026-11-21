<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Paper extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $casts = ['preprint_on' => 'date', 'tags' => 'array'];

    public const STAGES = [
        'idea' => 'Idea',
        'drafting' => 'Drafting',
        'ready' => 'Ready to submit',
        'under_review' => 'Under review',
        'revision' => 'Revising',
        'accepted' => 'Accepted',
        'published' => 'Published',
        'shelved' => 'Shelved',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class)->orderByDesc('submitted_on')->orderByDesc('id');
    }

    public function currentSubmission(): HasOne
    {
        // A submission marked as current wins; otherwise the most recent one.
        return $this->hasOne(Submission::class)->ofMany(['is_current' => 'max', 'submitted_on' => 'max', 'id' => 'max']);
    }

    public function queue(): HasMany
    {
        return $this->hasMany(QueueItem::class)->orderBy('position');
    }

    public function authors(): HasMany
    {
        return $this->hasMany(Author::class)->orderBy('position');
    }

    public function files(): HasMany
    {
        return $this->hasMany(PaperFile::class)->latest();
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class)->orderBy('due_on');
    }

    public function displayTitle(): string
    {
        return $this->short_title ?: $this->title;
    }

    /** A public preprint while an anonymous review is pending. */
    public function anonymityRisk(): ?Submission
    {
        if (! $this->preprint_url) {
            return null;
        }
        $s = $this->currentSubmission;

        return $s && $s->isActive() && $s->venue?->isAnonymous() ? $s : null;
    }

    /** Rejected or withdrawn last time, with nothing under review now. */
    public function needsNextVenue(): bool
    {
        $s = $this->currentSubmission;

        return $s && $s->isRejection() && ! in_array($this->stage, ['accepted', 'published', 'shelved']);
    }

    /** Submit to the first venue in the queue, today. */
    public function submitToNext(): ?Submission
    {
        $next = $this->queue()->with('venue')->first();
        if (! $next) {
            return null;
        }
        $this->submissions()->update(['is_current' => false]);
        $sub = $this->submissions()->create([
            'venue_id' => $next->venue_id,
            'venue_name' => $next->venue->name,
            'portal_url' => $next->venue->portal_url,
            'submitted_on' => now()->toDateString(),
            'decision' => 'pending',
        ]);
        $next->delete();
        $this->update(['stage' => 'under_review']);

        return $sub;
    }
}
