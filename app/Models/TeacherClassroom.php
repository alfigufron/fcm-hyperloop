<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TeacherClassroom extends Pivot
{
    protected $fillable = [
        'teacher_id', 'classroom_id', 'deleted_at', 'school_year'
    ];
}
