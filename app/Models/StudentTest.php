<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTest extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'test_id', 'student_id', 'start_at', 'end_at', 'status', 'is_allowed', 'score', 'student_start_at', 'student_end_at'
    ];

    public function test(){
        return $this->belongsTo('App\Models\Test', 'test_id');
    }

    public function student(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }

    public function student_test_answers(){
        return $this->hasMany('App\Models\StudentTestAnswer');
    }

    public function student_test_question_items(){
        return $this->hasMany('App\Models\StudentTestQuestionItem');
    }
}
