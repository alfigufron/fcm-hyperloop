<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'nps', 'address',
    ];

    public function admin(){
        return $this->hasOne('App\Models\SchoolAdmin');
    }

    public function agendas(){
        return $this->belongsToMany('App\Models\Agenda', 'school_agendas')
            ->using('App\Models\SchoolAgenda');
    }

    public function announcements(){
        return $this->belongsToMany('App\Models\Announcement', 'school_announcements')
            ->using('App\Models\SchoolAnnouncement');
    }

    public function attendance(){
        return $this->belongsToMany('App\Models\Attendance', 'school_attendances')
            ->using('App\Models\SchoolAttendance');
    }

    public function classrooms(){
        return $this->belongsToMany('App\Models\Classroom', 'school_classrooms')
            ->using('App\Models\SchoolClassroom');
    }

    public function dormitories(){
        return $this->hasMany('App\Models\Dormitory');
    }

    public function learningcontracts(){
        return $this->belongsToMany('App\Models\LearningContract', 'school_learning_contracts')
            ->using('App\Models\SchoolLearningContract');
    }

    public function questions(){
        return $this->belongsToMany('App\Models\Question', 'school_questions')
            ->using('App\Models\SchoolQuestion');
    }

    public function schedules(){
        return $this->belongsToMany('App\Models\Schedule', 'school_schedules')
            ->using('App\Models\SchoolSchedule');
    }

    public function subjects(){
        return $this->belongsToMany('App\Models\Subject', 'school_subjects')
            ->using('App\Models\SchoolSubject')
            ->withPivot('color');
    }

    public function teachers(){
        return $this->belongsToMany('App\Models\Teacher', 'school_teachers')
            ->using('App\Models\SchoolTeacher');
    }

    public function handling(){
        return $this->hasMany('App\Models\StudentHandlingOver');
    }

    public function institutions(){
        return $this->belongsTo('App\Models\Institution', 'institution_id');
    }

    public function levels(){
        return $this->belongsTo('App\Models\SchoolLevel', 'school_level_id');
    }

    public function students(){
        return $this->belongsToMany('App\Models\Student', 'school_students')
            ->using('App\Models\SchoolStudent');
    }

    public function school_minimum_competency(){
        return $this->hasOne('App\Models\SchoolMinimumCompetency');
    }

    public function lessons(){
        return $this->belongsToMany('App\Models\Lesson', 'school_lessons')
            ->using('App\Models\SchoolLesson');
    }
}
