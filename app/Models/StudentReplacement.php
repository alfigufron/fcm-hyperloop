<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StudentReplacement extends Pivot
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'student_replacements';
    protected $fillable = [
        'student_id', 'classroom_id', 'schedule_id', 'status', 'old_schedule_id'
    ];

    public function students(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }
}
