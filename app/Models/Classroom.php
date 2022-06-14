<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'teacher_id', 'code', 'name', 'grade', 'classroom_type', 'major_id', 'capacity', 'deleted_at',
    ];

    public function classroom_students(){
        return $this->hasMany('App\Models\ClassroomStudent');
    }

    public function schools(){
        return $this->belongsToMany('App\Models\School' ,'school_classrooms')
            ->using('App\Models\SchoolClassroom');
    }

    public function dormitories(){
        return $this->belongsToMany('App\Models\Dormitory' ,'dormitory_classrooms')
            ->using('App\Models\DormitoryClassroom');
    }

    public function students(){
        return $this->belongsToMany('App\Models\Student', 'classroom_students')
            ->using('App\Models\ClassroomStudent')
            ->withPivot('is_leader', 'is_active', 'school_year');
    }

    public function teacher_classrooms(){
        return $this->belongsToMany('App\Models\Teacher', 'teacher_classrooms')
            ->using('App\Models\TeacherClassroom');
    }

    public function assignments(){
        return $this->hasMany('App\Models\Assignment');
    }

    public function tests(){
        return $this->hasMany('App\Models\Test');
    }

    public function schedules(){
        return $this->hasMany('App\Models\Schedule');
    }

    public function lessons(){
        return $this->hasMany('App\Models\Lesson');
    }

    public function teachers(){
        return $this->belongsTo('App\Models\Teacher', 'teacher_id');
    }

    public function major(){
        return $this->belongsTo('App\Models\Major', 'major_id');
    }

    public function replacement_students(){
        return $this->belongsToMany('App\Models\Student', 'student_replacements')
            ->using('App\Models\StudentReplacement')
            ->withPivot('schedule_id', 'status', 'old_schedule_id');
    }

    public function replacement_schedules(){
        return $this->belongsToMany('App\Models\Schedule', 'student_replacements')
            ->using('App\Models\StudentReplacement')
            ->withPivot('student_id', 'status', 'old_schedule_id');
    }

    public function scopeWhereLike($query, $column, $value){
        return $query->where($column, 'like', '%'.$value.'%');
    }

    public function scopeStrIntOrderBy($query, $column, $sort){
        return $query->orderByRaw("NULLIF(regexp_replace($column, '\D', '', 'g'), '')::int $sort");
    }
}
