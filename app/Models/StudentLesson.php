<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentLesson extends Pivot
{
    use SoftDeletes;

    protected $table = 'student_lessons';

    protected $fillable = ['student_id', 'lesson_id', 'is_additional', 'deleted_at'];

    protected $dates = ['deleted_at'];
}
