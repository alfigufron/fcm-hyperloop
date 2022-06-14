<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StudentOffence extends Pivot
{
    protected $table = 'student_offences';
}
