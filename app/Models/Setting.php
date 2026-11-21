<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/** App-wide settings saved from the Settings page. Secrets are encrypted with the app key. */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['encrypted' => 'boolean'];

    /** Map of setting keys to the config keys they override. */
    public const CONFIG = [
        'ai.driver' => 'services.ai.driver',
        'ai.claude_bin' => 'services.ai.claude_bin',
        'ai.claude_token' => 'services.ai.claude_token',
        'ai.claude_model' => 'services.ai.claude_model',
        'anthropic.key' => 'services.anthropic.key',
        'anthropic.model' => 'services.anthropic.model',
        'openai.key' => 'services.openai.key',
        'openai.model' => 'services.openai.model',
        'gemini.key' => 'services.gemini.key',
        'gemini.model' => 'services.gemini.model',
        'compatible.base_url' => 'services.compatible.base_url',
        'compatible.key' => 'services.compatible.key',
        'compatible.model' => 'services.compatible.model',
    ];

    public const SECRET = ['ai.claude_token', 'anthropic.key', 'openai.key', 'gemini.key', 'compatible.key'];

    public static function put(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            static::whereKey($key)->delete();

            return;
        }
        $secret = in_array($key, self::SECRET, true);
        static::updateOrCreate(['key' => $key], ['value' => $secret ? Crypt::encryptString($value) : $value, 'encrypted' => $secret]);
    }

    public function plain(): ?string
    {
        try {
            return $this->encrypted ? Crypt::decryptString($this->value) : $this->value;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Copy saved settings over the .env defaults. Safe before migrations have run. */
    public static function applyToConfig(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
            foreach (static::all() as $s) {
                if (isset(self::CONFIG[$s->key]) && ($v = $s->plain()) !== null) {
                    config([self::CONFIG[$s->key] => $v]);
                }
            }
        } catch (\Throwable) {
        }
    }
}
