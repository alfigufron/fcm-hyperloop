<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use SoftDeletes;

    protected $table = 'lessons';

    protected $fillable = ['teacher_id', 'classroom_id', 'subject_id', 'title', 'description', 'grade', 'smester','learning_contract_information_id'];

    protected $dates = ['deleted_at'];

    public function classroom(){
        return $this->belongsTo('App\Models\Classroom', 'classroom_id');
    }

    public function lesson_medias(){
        return $this->hasMany('App\Models\LessonMedia');
    }

    public function student_lessons(){
        return $this->hasMany('App\Models\StudentLesson');
    }

    public function teacher(){
        return $this->belongsTo('App\Models\Teacher', 'teacher_id');
    }

    public function learning_contract_informations(){
        return $this->belongsTo('App\Models\LearningContractInformation', 'learning_contract_information_id');
    }

    public function students(){
        return $this->belongsToMany('App\Models\Student', 'student_lessons')
            ->using('App\Models\StudentLesson')
            ->withPivot('is_additional', 'deleted_at');
    }

    public function medias(){
        return $this->belongsToMany('App\Models\Media', 'lesson_media')
            ->using('App\Models\LessonMedia');
    }

    public function teachers(){
        return $this->belongsTo('App\Models\Teacher', 'teacher_id');
    }

    public function classrooms(){
        return $this->belongsTo('App\Models\Classroom', 'classroom_id');
    }

    public function subject(){
        return $this->belongsTo('App\Models\Subject','subject_id');
    }

    public function assignments(){
        return $this->hasMany('App\Models\Assignment');
    }

    public function tests(){
        return $this->hasMany('App\Models\Assignment');
    }

    public function schools(){
        return $this->belongsToMany('App\Models\School', 'school_lessons')
            ->using('App\Models\SchoolLesson');
    }

    public function dormitories(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_lessons')
            ->using('App\Models\DormitoryLesson');
    }


    /**
     * DELETE WITH ALL RELATED DATA
     *
     */
    public function deleteWithAllRelatedData(){
        // Delete Lesson Media
        $this->lesson_medias()->delete();

        // Delete Student Lesson
        $this->student_lessons()->delete();

        // Delete Lesson
        return parent::delete();
    }
}
