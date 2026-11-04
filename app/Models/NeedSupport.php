<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The `need_supports` pivot as a model: the compiled rung has no relation
 * objects, so the profile page reads this table directly.
 */
#[Fillable(['need_id', 'profile_id'])]
class NeedSupport extends Model
{
    protected $table = 'need_supports';
}
