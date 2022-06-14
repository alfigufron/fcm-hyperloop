<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DormitoryStudent extends Pivot
{
    protected $table = 'dormitory_students';

    protected $fillable = ['dormitory_id', 'student_id'];

    /**
     * DORMITORY
     *
     */
    public function dormitory(){
        return $this->belongsTo('App\Models\Dormitory');
    }
}
