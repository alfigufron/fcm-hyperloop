<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'classroom_id', 'learning_contract_information_id', 'subject_id', 'title', 'description', 'total_point', 'deadline', 'link', 'deleted_at'
    ];

    public function assignment_attempts(){
        return $this->hasMany('App\Models\AssignmentAttempt');
    }

    public function medias(){
        return $this->belongsToMany('App\Models\Media', 'assignment_media')
            ->using('App\Models\AssignmentMedia');
    }

    public function classroom(){
        return $this->belongsTo('App\Models\Classroom', 'classroom_id');
    }

    public function student_assignments(){
        return $this->hasMany('App\Models\StudentAssignment');
    }

    public function subject(){
        return $this->belongsTo('App\Models\Subject', 'subject_id');
    }

    public function learning_contract_information(){
        return $this->belongsTo('App\Models\LearningContractInformation', 'learning_contract_information_id');
    }

    public function lessons(){
        return $this->belongsTo('App\Models\Lesson', 'lesson_id');
    }
}
