<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentAssignment extends Model
{
    protected $table = 'student_assignments';

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_id', 'assignment_id', 'answer', 'score', 'status', 'finish_at', 'deleted_at','deadline'
    ];

    public function assignment(){
        return $this->belongsTo('App\Models\Assignment', 'assignment_id');
    }

    public function students(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }

    public function medias(){
        return $this->belongsToMany('App\Models\Media', 'student_assignment_media')
            ->using('App\Models\StudentAssignmentMedia');
    }

    public function student_assignment_attempts(){
        return $this->hasMany('App\Models\StudentAssignmentAttempt');
    }
}
