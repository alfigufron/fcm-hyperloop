<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAttendance extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'teacher_attendances';

    protected $fillable = [
        'schedule_id',
        'teacher_id',
        'status',
        'notes',
    ];

    public function teacher(){
        return $this->belongsTo('App\Models\Teacher', 'teacher_id');
    }

    public function schedule(){
        return $this->belongsTo('App\Models\Schedule', 'schedule_id');
    }
}
