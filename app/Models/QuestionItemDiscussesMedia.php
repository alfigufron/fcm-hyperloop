<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionItemDiscussesMedia extends Pivot
{

    use SoftDeletes;

    protected $table = 'question_item_discusses_media';

    protected $fillable = ['question_item_id', 'media_id'];
}
