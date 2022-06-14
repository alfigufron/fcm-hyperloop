<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestQuestionItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'test_id', 'question_item_id', 'score',
    ];

    public function student_test_question_item(){
        return $this->hasMany('App\Models\StudentTestQuestionItem');
    }

    public function question_item(){
        return $this->belongsTo('App\Models\QuestionItem', 'question_item_id');
    }

    public function test(){
        return $this->belongsTo('App\Models\Test', 'test_id');
    }

    public function student_test_answers(){
        return $this->hasMany('App\Models\StudentTestAnswer');
    }
}
