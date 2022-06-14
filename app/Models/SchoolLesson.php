<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SchoolLesson extends Pivot
{
    protected $table = 'dormitory_lessons';
}
