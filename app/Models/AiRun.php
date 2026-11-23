<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One long AI job (a scan or a batch of updates), with live progress for the sidebar. */
class AiRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['finished_at' => 'datetime'];

    public static function begin(User $user, string $kind, string $label, int $total = 0, ?string $url = null): self
    {
        return self::create(['user_id' => $user->id, 'kind' => $kind, 'label' => $label, 'total' => $total, 'url' => $url, 'step' => 'Starting…',
            'provider' => \App\Support\VenueResearcher::status()]);
    }

    public function step(string $step, ?int $done = null): self
    {
        $this->note($step);
        $this->update(array_filter(['step' => mb_substr($step, 0, 250), 'done' => $done], fn ($v) => $v !== null));

        return $this;
    }

    public function advance(?string $step = null): self
    {
        $this->increment('done');

        return $step ? $this->step($step) : $this;
    }

    /** Append a timestamped line to the job's log. */
    public function note(string $line): self
    {
        $this->log = ltrim(($this->log ?? '')."\n".now()->format('H:i:s').'  '.$line);
        $this->log = mb_substr($this->log, -20000);
        $this->save();

        return $this;
    }

    public function finish(?string $error = null): self
    {
        $this->note($error ? 'Failed: '.$error : 'Finished');
        $this->update(['status' => $error ? 'failed' : 'done', 'error' => $error ? mb_substr($error, 0, 600) : null,
            'finished_at' => now(), 'done' => $error ? (int) $this->done : max((int) $this->done, (int) $this->total)]);

        return $this;
    }

    public function percent(): ?int
    {
        return $this->total > 0 ? (int) min(100, round($this->done / $this->total * 100)) : null;
    }
}
