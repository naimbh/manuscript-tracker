<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $casts = ['sources' => 'array', 'checked_at' => 'datetime', 'citations' => 'integer', 'year' => 'integer'];

    public const KINDS = ['article' => 'Journal article', 'conference' => 'Conference paper', 'letter' => 'Letter / short paper',
        'chapter' => 'Book chapter', 'thesis' => 'Thesis', 'preprint' => 'Preprint'];

    public function citation(): string
    {
        $vol = $this->volume ? $this->volume.($this->issue ? "({$this->issue})" : '') : null;

        return collect([$this->venue, $vol, $this->pages ? 'pp. '.$this->pages : null])->filter()->implode(', ');
    }
}
