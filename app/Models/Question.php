<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subject_id', 'title','code', 'grade', 'semester',
    ];

    public function dormitories(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_questions')
            ->using('App\Models\DormitoryQuestion');
    }

    public function schools(){
        return $this->belongsToMany('App\Models\School', 'school_questions')
            ->using('App\Models\SchoolQuestion');
    }

    public function question_items(){
        return $this->hasMany('App\Models\QuestionItem');
    }

    public function subjects(){
        return $this->belongsTo('App\Models\Subject', 'subject_id');
    }

    public function teacher(){
        return $this->belongsTo('App\Models\Teacher', 'teacher_id');
    }
}
