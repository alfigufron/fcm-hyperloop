<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassroomStudent extends Pivot
{

    use SoftDeletes;

    protected $table = 'classroom_students';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'classroom_id', 'student_id', 'is_leader', 'is_active', 'school_year'
    ];

    /**
     * CLASSROOM
     *
     */
    public function classroom(){
        return $this->belongsTo('App\Models\Classroom');
    }

    /**
     * STUDENT
     *
     */
    public function student(){
        return $this->belongsTo('App\Models\Student');
    }
}
