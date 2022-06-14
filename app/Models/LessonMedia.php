<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonMedia extends Pivot
{
    use SoftDeletes;

    protected $table = 'lesson_media';

    protected $fillable = ['lesson_id', 'media_id'];

    protected $dates = ['deleted_at'];
}
