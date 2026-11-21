<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PaperFile extends Model
{
    protected $guarded = ['id'];

    public const KINDS = [
        'manuscript' => 'Manuscript',
        'anonymized' => 'Anonymized manuscript',
        'cover' => 'Cover letter',
        'response' => 'Response to reviewers',
        'proof' => 'Proof PDF',
        'supplementary' => 'Supplementary',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (PaperFile $f) => Storage::disk('local')->delete($f->path));
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf' || str_ends_with(strtolower($this->original_name), '.pdf');
    }

    public function humanSize(): string
    {
        $b = $this->size;

        return $b >= 1048576 ? round($b / 1048576, 1).' MB' : max(1, round($b / 1024)).' KB';
    }
}
