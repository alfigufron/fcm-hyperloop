<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTestQuestionItem extends Model
{
    protected $table = 'student_test_question_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'test_question_item_id', 'student_test_id', 'order'
    ];

    public function student_test_answers(){
        return $this->hasOne('App\Models\StudentTestAnswer');
    }

    public function student_test(){
        return $this->belongsTo('App\Models\StudentTest', 'student_test_id');
    }

    public function test_question_items(){
        return $this->belongsTo('App\Models\TestQuestionItem', 'test_question_item_id');
    }
}
