<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Test extends Model
{

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'classroom_id', 'learning_contract_information_id', 'subject_id', 'title', 'test_type', 'start_at', 'end_at', 'duration', 'total_point', 'grade', 'semester'
    ];

    public function classroom(){
        return $this->belongsTo('App\Models\Classroom');
    }

    public function student_tests(){
        return $this->hasMany('App\Models\StudentTest');
    }

    public function test_attempts(){
        return $this->hasMany('App\Models\TestAttempt');
    }

    public function subject(){
        return $this->belongsTo('App\Models\Subject', 'subject_id');
    }

    public function test_question_items(){
        return $this->hasMany('App\Models\TestQuestionItem');
    }

    public function learning_contract_information(){
        return $this->belongsTo('App\Models\LearningContractInformation', 'learning_contract_information_id');
    }

    public function lessons(){
        return $this->belongsTo('App\Models\Lesson', 'lesson_id');
    }
}
