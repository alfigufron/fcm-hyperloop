<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionItem extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'question_id', 'question', 'level', 'question_item_type',
    ];

    public function test_question_item(){
        return $this->hasMany('App\Models\TestQuestionItem');
    }

    public function question_item_answers(){
        return $this->hasMany('App\Models\QuestionItemAnswer');
    }

    public function question_item_media(){
        return $this->hasOne('App\Models\QuestionItemMedia');
    }

    public function question_item_discussses(){
        return $this->hasMany('App\Models\QuestionItemDiscuss');
    }

    public function questions(){
        return $this->belongsTo('App\Models\Question', 'question_id');
    }

    public function media(){
        return $this->belongsToMany('App\Models\Media', 'question_item_media')
            ->using('App\Models\QuestionItemMedia');
    }
}
