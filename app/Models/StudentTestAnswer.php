<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTestAnswer extends model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'student_test_answers';

    protected $fillable = [
        'student_test_id', 'test_question_item_id', 'media_id', 'answer', 'score','student_test_question_item_id'
    ];


    public function student_question_items(){
        return $this->belongsTo('App\Models\StudentTestQuestionItem', 'student_test_question_item_id');
    }

    public function student_test(){
        return $this->belongsTo('App\Models\StudentTest', 'student_test_id');
    }

    public function test_questions(){
        return $this->belongsTo('App\Models\TestQuestionItem', 'test_question_item_id');
    }

    public function media(){
        return $this->belongsTo('App\Models\Media', 'media_id');
    }
}
