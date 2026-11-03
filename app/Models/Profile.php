<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

#[Fillable([
    'email',
    'name',
    'username',
    'phone',
    'bio',
    'avatar_path',
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
    public function notificationSettings(): array
    {
        return array_merge([
            'need_updates' => true,
            'support_activity' => true,
            'weekly_digest' => false,
        ], $this->notification_preferences ?? []);
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    public function initials(): string
    {
        $words = collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->take(2);

        if ($words->isEmpty()) {
            return 'NF';
        }

        return $words
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }

    public function displayUsername(): string
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
