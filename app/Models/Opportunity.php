<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $casts = ['deadline_on' => 'date'];

    public const KINDS = ['conference' => 'Conference', 'special_issue' => 'Special issue', 'journal' => 'Journal call', 'workshop' => 'Workshop'];
}
