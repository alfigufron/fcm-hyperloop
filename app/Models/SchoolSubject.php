<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SchoolSubject extends Pivot
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'school_subjects';
    
    protected $fillable = [
        'color'
    ];
}
