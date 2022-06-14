<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DormitoryClassroom extends Pivot
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'dormitory_classrooms';
    
    protected $fillable = [
        'dormitory_id', 'classroom_id'
    ];
}
