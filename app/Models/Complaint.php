<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'category', 'due_date', 'description', 'image', 'status', 'is_reported_by_student',
    ];

//    public function teachercomplaints(){
//        return $this->belongsToMany('App\Models\Teacher', 'teacher_complaints')
//            ->using('App\Models\TeacherComplaint');
//    }

    public function teacher(){
        return $this->belongsTo('App\Models\Teacher', 'teacher_id');
    }

    public function student(){
        return $this->belongsTo('App\Models\Student','student_id');
    }
}
