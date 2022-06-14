<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ScheduleAttendance extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'schedule_attendances';

    protected $fillable = [
        'schedule_id', 'student_id', 'status', 'notes', 'media_id',
    ];

    public function schedule(){
        return $this->belongsTo('App\Models\Schedule','schedule_id');
    }

    public function student(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }
}
