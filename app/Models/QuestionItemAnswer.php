<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionItemAnswer extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'question_item_id', 'answer', 'is_correct',
    ];

    public function questions(){
        return $this->belongsTo('App\Models\QuestionItem');
    }

    public function media(){
        return $this->belongsToMany('App\Models\Media', 'question_item_answer_media')
            ->using('App\Models\QuestionItemAnswerMedia');
    }
}
