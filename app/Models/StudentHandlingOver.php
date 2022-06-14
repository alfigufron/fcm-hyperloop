<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentHandlingOver extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'department_target', 'status', 'left_at', 'arrived_at',
    ];

    public function students(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }

    public function schools(){
        return $this->belongsTo('App\Models\School', 'school_id');
    }

    public function dormitories(){
        return $this->belongsTo('App\Models\Dormitory', 'dormitory_id');
    }
}
