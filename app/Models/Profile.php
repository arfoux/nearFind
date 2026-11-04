<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Throwable;

#[Fillable([
    'email',
    'name',
    'username',
    'phone',
    'bio',
    'password_hash',
    'notification_preferences',
])]
#[Hidden(['password_hash', 'notification_preferences'])]
class Profile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_preferences' => 'array',
        ];
    }

    public function needs(): HasMany
    {
        return $this->hasMany(Need::class);
    }

    public function supportedNeeds(): BelongsToMany
    {
        return $this->belongsToMany(Need::class, 'need_supports')
            ->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * @return array{need_updates: bool, support_activity: bool, weekly_digest: bool}
     */
    public function getNotificationSettingsAttribute(): array
    {
        return array_merge([
            'need_updates' => true,
            'support_activity' => true,
            'weekly_digest' => false,
        ], $this->notification_preferences ?? []);
    }

    /**
     * The default avatar: one of six palettes, chosen deterministically from the
     * row id (no upload, no storage, no asset request). The matching background
     * lives in `resources/css/app.css` as `.nf-avatar--0..5`.
     */
    public function getAvatarVariantAttribute(): int
    {
        return ((int) $this->id) % 6;
    }

    public function getInitialsAttribute(): string
    {
        $letters = '';

        foreach (explode(' ', trim((string) $this->name)) as $word) {
            if ($word === '') {
                continue;
            }

            $letters .= mb_strtoupper(mb_substr($word, 0, 1));

            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : 'NF';
    }

    public function getDisplayUsernameAttribute(): string
    {
        if (is_string($this->username) && $this->username !== '') {
            return '@'.$this->username;
        }

        return '@'.Str::before($this->email, '@');
    }

    public static function findByEmail(string $email): ?self
    {
        try {
            return static::query()->where('email', $email)->first();
        } catch (Throwable) {
            return null;
        }
    }

    public static function displayNameForEmail(string $email): ?string
    {
        $name = static::findByEmail($email)?->name;

        return is_string($name) && $name !== '' ? $name : null;
    }

    public static function usernameFromEmail(string $email): string
    {
        $base = Str::of(Str::before($email, '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '')
            ->trim('._-')
            ->limit(24, '')
            ->value();

        return $base !== '' ? $base : 'warga';
    }
}
