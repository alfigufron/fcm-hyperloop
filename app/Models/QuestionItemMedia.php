<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionItemMedia extends Pivot
{

    use SoftDeletes;

    protected $table = 'question_item_media';

    protected $fillable = ['question_item_id', 'media_id'];

    public function media(){
        return $this->belongsTo('App\Models\Media');
    }
}
