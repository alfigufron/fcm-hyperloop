<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'nis', 'entrance_year', 'status', 'status_description', 'learning_type', 'is_transfer', 'school_origin',
    ];

    public function test_attempts(){
        return $this->hasMany('App\Models\TestAttempt');
    }

    public function achievement(){
        return $this->belongsToMany('App\Models\StudentAchievement', 'student_achievement_teams')
            ->using('App\Models\StudentAchievementTeam')
            ->withPivot('is_pillar');
    }

    public function achievement_dashboard(){
        return $this->belongsToMany('App\Models\StudentAchievement', 'student_achievement_teams')
            ->limit(5)
            ->using('App\Models\StudentAchievementTeam');
    }

    public function adviser(){
        return $this->belongsToMany('App\Models\Teacher', 'adviser_students')
            ->using('App\Models\AdviserStudent');
    }

    public function classroom(){
        return $this->belongsToMany('App\Models\Classroom', 'classroom_students')
            ->using('App\Models\ClassroomStudent')
            ->withPivot('is_leader', 'is_active', 'school_year');
    }

    public function classrooms(){
        return $this->belongsToMany('App\Models\Classroom', 'classroom_students')
            ->using('App\Models\ClassroomStudent')
            ->withPivot('is_leader', 'is_active', 'school_year');
    }

    public function dormitoryrooms(){
        return $this->belongsToMany('App\Models\DormitoryRoom', 'student_dormitory_rooms')
            ->using('App\Models\StudentDormitoryRoom');
    }

    public function student_families(){
        return $this->hasMany('App\Models\StudentFamily');
    }

    public function families(){
        return $this->belongsToMany('App\Models\Family', 'student_families')
            ->using('App\Models\StudentFamily');
    }

    public function lessons(){
        return $this->belongsToMany('App\Models\Lesson', 'student_lessons')
            ->using('App\Models\StudentLesson')
            ->withPivot('is_additional');
    }

    public function schedule_attendances(){
        return $this->hasMany('App\Models\ScheduleAttendance');
    }

    public function scheduleattendances(){
        return $this->belongsToMany('App\Models\Schedule', 'schedule_attendances')
            ->using('App\Models\ScheduleAttendance')
            ->withPivot('status', 'notes', 'media_id');
    }

    public function attendances(){
        return $this->hasMany('App\Models\Attendance');
    }

    public function student_assignments(){
        return $this->hasMany('App\Models\StudentAssignment');
    }

    public function assignment_attempts(){
        return $this->hasMany('App\Models\AssignmentAttempt');
    }

    public function student_tests(){
        return $this->hasMany('App\Models\StudentTest');
    }

    public function complaints(){
        return $this->hasMany('App\Models\Complaint');
    }

    public function dormitorylefts(){
        return $this->hasMany('App\Models\DormitoryLeftPermission');
    }

    public function student_detail(){
        return $this->hasOne('App\Models\StudentDetail');
    }

    public function handlings(){
        return $this->hasMany('App\Models\StudentHandlingOver');
    }

    public function invoices(){
        return $this->hasMany('App\Models\StudentInvoice');
    }

    public function medicalrecords(){
        return $this->hasMany('App\Models\StudentMedicalRecord');
    }

    public function penalties(){
        return $this->hasMany('App\Models\StudentPenalty');
    }

    public function religion(){
        return $this->belongsTo('App\Models\Religion', 'religion_id');
    }

    public function schools(){
        return $this->belongsToMany('App\Models\School', 'school_students')
            ->using('App\Models\SchoolStudent');
    }

    public function dormitories(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_students')
            ->using('App\Models\DormitoryStudent')
            ->withPivot('is_active', 'school_year');
    }

    public function tests(){
        return $this->hasMany('App\Models\Test');
    }

    public function user(){
        return $this->belongsTo('App\Models\User');
    }

    public function replacement_classrooms(){
        return $this->belongsToMany('App\Models\Classroom', 'student_replacements')
            ->using('App\Models\StudentReplacement')
            ->withPivot('schedule_id', 'status', 'old_schedule_id');
    }

    public function replacement_schedules(){
        return $this->belongsToMany('App\Models\Schedule', 'student_replacements')
            ->using('App\Models\StudentReplacement')
            ->withPivot('classroom_id', 'status', 'old_schedule_id');
    }

    public function dormitory_students(){
        return $this->hasMany('App\Models\DormitoryStudent');
    }

    public function offences(){
        return $this->belongsToMany('App\Models\Offence', 'student_offences')
            ->using('App\Models\StudentOffence');
    }

    public function current_classroom(){
        $result = ClassroomStudent::where('student_id', $this->id)->where('is_active', TRUE)->with('classroom')->first();

        return $result->classroom;
    }
}
