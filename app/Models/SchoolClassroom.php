<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SchoolClassroom extends Pivot
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'school_classrooms';
    
    protected $fillable = [
        'school_id', 'classroom_id'
    ];
}
