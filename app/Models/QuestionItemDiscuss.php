<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionItemDiscuss extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'question_item_id', 'discuss',
    ];

    public function questions(){
        return $this->belongsTo('App\Models\QuestionItem', 'question_item_id');
    }

    public function media(){
        return $this->belongsToMany('App\Models\Media', 'question_item_discuss_media')
            ->using('App\Models\QuestionItemDiscussesMedia');
    }
}
