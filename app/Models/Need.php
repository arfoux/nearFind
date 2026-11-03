<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['profile_id', 'title', 'category', 'location', 'status'])]
class Need extends Model
{
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function supports(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'need_supports')
            ->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
