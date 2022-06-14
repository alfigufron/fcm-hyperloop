<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StudentAssignmentMedia extends Pivot
{
    protected $table = 'student_assignment_media';

    protected $fillable = ['student_assignment_id', 'media_id'];
}
