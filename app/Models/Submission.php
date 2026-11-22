<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_current' => 'boolean', 'submitted_on' => 'date', 'status_on' => 'date', 'decision_on' => 'date', 'revision_due_on' => 'date'];

    public const DECISIONS = [
        'submitted' => 'Submitted',
        'with_editor' => 'With editor',
        'pending' => 'Under review',
        'awaiting_decision' => 'Awaiting decision',
        'returned' => 'Returned to author',
        'desk_reject' => 'Desk rejected',
        'reject' => 'Rejected',
        'major' => 'Major revision',
        'minor' => 'Minor revision',
        'accept' => 'Accepted',
        'withdrawn' => 'Withdrawn',
    ];

    /** Statuses where the paper is with the journal and no decision has been made yet. */
    public const WAITING = ['submitted', 'with_editor', 'pending', 'awaiting_decision', 'returned'];

    /** The paper stage that matches a submission status. */
    public static function stageFor(string $decision): string
    {
        return match ($decision) {
            'submitted', 'with_editor', 'returned' => 'submitted',
            'pending', 'awaiting_decision' => 'under_review',
            'major', 'minor' => 'revision',
            'accept' => 'accepted',
            default => 'ready',
        };
    }

    public function isWaiting(): bool
    {
        return in_array($this->decision, self::WAITING, true);
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function revisionItems(): HasMany
    {
        return $this->hasMany(RevisionItem::class)->orderBy('position');
    }

    public function isActive(): bool
    {
        return $this->isWaiting() || in_array($this->decision, ['major', 'minor'], true);
    }

    public function isRejection(): bool
    {
        return in_array($this->decision, ['desk_reject', 'reject', 'withdrawn'], true);
    }

    public function daysInReview(): ?int
    {
        if (! $this->submitted_on) {
            return null;
        }
        $end = $this->isWaiting() ? now() : ($this->decision_on ?? now());
        // While under review, count from the day review started if that is known.
        $start = $this->decision === 'pending' && $this->status_on ? $this->status_on : $this->submitted_on;

        return (int) $start->diffInDays($end);
    }

    /** Fraction of the venue's typical decision time used so far, or null. */
    public function clock(): ?array
    {
        $days = $this->daysInReview();
        $typical = $this->venue?->decision_days;
        if (! $this->isWaiting() || $days === null || ! $typical) {
            return null;
        }

        return [
            'days' => $days,
            'typical' => $typical,
            'pct' => min(100, (int) round($days / $typical * 100)),
            'overdue' => $days > $typical,
            'expected_on' => ($this->decision === 'pending' && $this->status_on ? $this->status_on : $this->submitted_on)->copy()->addDays($typical),
        ];
    }

    public function tone(): string
    {
        return match ($this->decision) {
            'accept' => 'green',
            'minor', 'major' => 'amber',
            'desk_reject', 'reject' => 'red',
            'withdrawn' => 'zinc',
            'returned' => 'amber',
            default => 'blue',
        };
    }
}
