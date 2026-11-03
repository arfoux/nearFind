<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['profile_id', 'need_id', 'need_title', 'category', 'reason', 'status'])]
class Report extends Model
{
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(Need::class);
    }
}
