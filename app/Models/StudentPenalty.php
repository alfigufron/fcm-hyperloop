<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPenalty extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'description', 'penalty_type', 'date', 'file'
    ];

    public function students(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }
}
