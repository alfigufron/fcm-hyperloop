<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignmentAttempt extends Model
{
    use SoftDeletes;

    protected $fillable = ['assignment_id' ,'student_id', 'score', 'status'];

    public function students(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }

    public function assignments(){
        return $this->belongsTo('App\Models\Assignment', 'assignment_id');
    }
}
