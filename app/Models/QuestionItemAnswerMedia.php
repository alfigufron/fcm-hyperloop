<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionItemAnswerMedia extends Pivot
{

    use SoftDeletes;

    protected $table = 'question_item_answer_media';

    protected $fillable = ['question_item_answer', 'media_id'];

    public function media(){
        return $this->belongsTo('App\Models\Media');
    }
}
