<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SchoolStudent extends Pivot
{
    protected $table = 'school_students';

    protected $fillable = ['school_id', 'student_id'];
}
