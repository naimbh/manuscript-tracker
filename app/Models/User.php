<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'scholar_stats' => 'array',
            'is_admin' => 'boolean',
            'scholar_checked_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            if (static::count() === 1) {
                $user->forceFill(['is_admin' => true])->saveQuietly();
            }
            \App\Support\VenueCatalog::seedFor($user);
        });
    }

    public function papers(): HasMany
    {
        return $this->hasMany(Paper::class);
    }

    public function aiRuns(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class)->latest();
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class)->orderBy('name');
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class)->orderBy('due_on');
    }

    public function calendarToken(): string
    {
        if (! $this->calendar_token) {
            $this->forceFill(['calendar_token' => Str::random(40)])->save();
        }

        return $this->calendar_token;
    }
}
