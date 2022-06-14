<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subject_id', 'teacher_id', 'classroom_id', 'learning_contract_information_id', 'main_topic', 'sub_topic', 'date', 'start_at', 'end_at', 'schedule_type', 'school_year', 'semester'
    ];

    public function schedule_attendances(){
        return $this->hasMany('App\Models\ScheduleAttendance');
    }

    public function teacher_attendances(){
        return $this->hasMany('App\Models\TeacherAttendance');
    }

    public function attendances(){
        return $this->belongsToMany('App\Models\Student', 'schedule_attendances')
            ->using('App\Models\ScheduleAttendance')
            ->withPivot('status', 'notes', 'media_id');
    }

    public function dormitory(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_schedules')
            ->using('App\Models\DormitorySchedule');
    }

    public function school(){
        return $this->belongsToMany('App\Models\School', 'school_schedules')
            ->using('App\Models\SchoolSchedule');
    }

    public function medias(){
        return $this->belongsToMany('App\Models\Media', 'schedule_media')
            ->using('App\Models\ScheduleMedia');
    }

    public function subjects(){
        return $this->belongsTo('App\Models\Subject', 'subject_id');
    }

    public function subject(){
        return $this->belongsTo('App\Models\Subject', 'subject_id');
    }

    public function teachers(){
        return $this->belongsTo('App\Models\Teacher', 'teacher_id');
    }

    public function teacher(){
        return $this->belongsTo('App\Models\Teacher', 'teacher_id');
    }

    public function classroom(){
        return $this->belongsTo('App\Models\Classroom', 'classroom_id');
    }

    public function classrooms(){
        return $this->belongsTo('App\Models\Classroom', 'classroom_id');
    }

    public function learning_contract_information(){
        return $this->belongsTo('App\Models\LearningContractInformation', 'learning_contract_information_id');
    }

    public function learning_contract(){
        return $this->belongsTo('App\Models\LearningContract', 'learning_contract_id');
    }

    public function video_meet(){
        return $this->hasOne('App\Models\VideoMeet');
    }

    public function replacement_students(){
        return $this->belongsToMany('App\Models\Student', 'student_replacements')
            ->using('App\Models\StudentReplacement')
            ->withPivot('classroom_id', 'status', 'old_schedule_id');
    }

    public function replacements(){
        return $this->hasMany('App\Models\StudentReplacement','schedule_id');
    }
    public function replacement_classrooms(){
        return $this->belongsToMany('App\Models\Classroom', 'student_replacements')
            ->using('App\Models\StudentReplacement')
            ->withPivot('student_id', 'status', 'old_schedule_id');
    }
}
