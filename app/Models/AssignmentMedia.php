<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignmentMedia extends Pivot
{

    use SoftDeletes;

    protected $table = 'assignment_media';

    protected $fillable = ['assignment_id', 'media_id'];
}
