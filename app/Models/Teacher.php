<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'teachers';

    protected $fillable = [
        'nip', 'name', 'address', 'date_of_birth', 'gender', 'is_adviser', 'profile_picture',
    ];

//    public function teachercomplaints(){
//        return $this->belongsToMany('App\Models\Complaint', 'teacher_complaints')
//            ->using('App\Models\TeacherComplaint');
//    }

    public function dormitories(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_teachers')
            ->using('App\Models\DormitoryTeacher');
    }
    public function schools(){
        return $this->belongsToMany('App\Models\School', 'school_teachers')
            ->using('App\Models\SchoolTeacher');
    }

    public function questions(){
        return $this->hasMany('App\Models\Question');
    }

    public function subjects(){
        return $this->belongsToMany('App\Models\Subject', 'teacher_subjects')
            ->using('App\Models\TeacherSubject');
    }

    public function teacher_classrooms(){
        return $this->belongsToMany('App\Models\Classroom', 'teacher_classrooms')
            ->using('App\Models\TeacherClassroom')
            ->withPivot('school_year');
    }

    public function teacher_attendances(){
        return $this->hasMany('App\Models\TeacherAttendance');
    }

    public function learningcontracts(){
        return $this->hasMany('App\Models\LearningContract');
    }

    public function classrooms(){
        return $this->hasMany('App\Models\Classroom');
    }

    public function schedules(){
        return $this->hasMany('App\Models\Schedule');
    }

    public function complaints(){
        return $this->hasMany('App\Models\Complaint');
    }

    public function lessons(){
        return $this->hasMany('App\Models\Lesson');
    }

    public function user(){
        return $this->belongsTo('App\Models\User');
    }

    public function city(){
        return $this->belongsTo('App\Models\City');
    }

    public function position(){
        return $this->belongsTo('App\Models\TeacherPosition');
    }

    public function religion(){
        return $this->belongsTo('App\Models\Religion', 'religion_id');
    }

    public function selectedSchool(){
        $a = SchoolTeacher::query()->where('teacher_id', $this->id)->first();
    }
}
