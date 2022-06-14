<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SchoolQuestion extends Pivot
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id', 'question_id'
    ];
}
