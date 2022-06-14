<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date', 'status', 'notes',
    ];

    public function school(){
        return $this->belongsToMany('App\Models\School', 'school_attendance')
            ->using('App\Models\SchoolAttendance');
    }

    public function dormitory(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_attendance')
            ->using('App\Models\DormitoryAttendance');
    }

    public function students(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }
}
