<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DormitoryQuestion extends Pivot
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'dormitory_id', 'question_id'
    ];
}
