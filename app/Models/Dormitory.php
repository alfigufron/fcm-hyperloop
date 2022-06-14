<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dormitory extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code', 'name', 'gender',
    ];

    public function admins(){
        return $this->hasOne('App\Models\DormitoryAdmin');
    }

    public function agendas(){
        return $this->belongsToMany('App\Models\Agenda', 'dormitory_agendas')
            ->using('App\Models\DormitoryAgenda');
    }

    public function announcements(){
        return $this->belongsToMany('App\Models\Announcement', 'dormitory_announcements')
            ->using('App\Models\DormitoryAnnouncement');
    }

    public function attendance(){
        return $this->belongsToMany('App\Models\Attendance', 'dormitory_attendance')
            ->using('App\Models\DormitoryAttendance');
    }

    public function classrooms(){
        return $this->belongsToMany('App\Models\Classroom', 'dormitory_classrooms')
            ->using('App\Models\DormitoryClassroom');
    }

    public function learningcontracts(){
        return $this->belongsToMany('App\Models\LearningContract', 'dormitory_learning_contracts')
            ->using('App\Models\DormitoryLearningContract');
    }

    public function questions(){
        return $this->belongsToMany('App\Models\Question', 'dormitory_questions')
            ->using('App\Models\DormitoryQuestion');
    }

    public function schedules(){
        return $this->belongsToMany('App\Models\Schedule', 'dormitory_schedules')
            ->using('App\Models\DormitorySchedule');
    }

    public function subjects(){
        return $this->belongsToMany('App\Models\Subject', 'dormitory_subjects')
            ->using('App\Models\DormitorySubject')
            ->withPivot('color');
    }

    public function teachers(){
        return $this->belongsToMany('App\Models\Teacher', 'dormitory_teachers')
            ->using('App\Models\DormitoryTeacher');
    }

    public function handlingover(){
        return $this->hasMany('App\Models\StudentHandlingOver');
    }

    public function leftpermissions(){
        return $this->hasMany('App\Models\DormitoryLeftPermission');
    }

    public function rooms(){
        return $this->hasMany('App\Models\DormitoryRoom');
    }

    public function schools(){
        return $this->belongsTo('App\Models\School', 'school_id');
    }

    public function students(){
        return $this->belongsToMany('App\Models\DormitoryStudent')
            ->using('App\Models\DormitoryStudent');
    }

    public function dormitory_minimum_competency(){
        return $this->hasOne('App\Models\DormitoryMinimumCompetency');
    }

    public function lessons(){
        return $this->belongsToMany('App\Models\Lesson', 'dormitory_lessons')
            ->using('App\Models\DormitoryLesson');
    }
}
