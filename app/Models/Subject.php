<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code', 'name', 'description'
    ];

    public function dormitories(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_subjects')
            ->using('App\Models\DormitorySubject')
            ->withPivot('color');
    }

    public function schools(){
        return $this->belongsToMany('App\Models\School', 'school_subjects')
            ->using('App\Models\SchoolSubject')
            ->withPivot('color');
    }

    public function teachers(){
        return $this->belongsToMany('App\Models\Teacher', 'teacher_subjects')
            ->using('App\Models\TeacherSubject');
    }

    public function learningcontract(){
        return $this->hasOne('App\Models\LearningContract');
    }

    public function assignment(){
        return $this->hasOne('App\Models\Assignment');
    }

    public function lesson(){
        return $this->hasOne('App\Models\Lesson');
    }

    public function major(){
        return $this->belongsTo('App\Models\Major');
    }

    public function questions(){
        return $this->hasMany('App\Models\Question');
    }

    public function schedules(){
        return $this->hasMany('App\Models\Schedule');
    }

    public function school_subjects(){
        return $this->hasMany('App\Models\SchoolSubject');
    }

    public function dormitory_subjects(){
        return $this->hasMany('App\Models\DormitorySubject');
    }

    public function test(){
        return $this->hasOne('App\Models\Test');
    }

    public function task(){
        return $this->hasOne('App\Models\Assignment');
    }
}
