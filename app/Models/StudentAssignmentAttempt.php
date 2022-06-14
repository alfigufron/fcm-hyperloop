<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAssignmentAttempt extends Model
{
    protected $table = 'student_assignment_attempts';

    protected $fillable = ['student_assignment_id', 'score', 'status'];
}
