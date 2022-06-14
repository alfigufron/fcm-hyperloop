<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestAttempt extends Model
{
    protected $table = 'test_attempts';

    protected $fillable = [
        'test_id', 'student_id', 'score', 'status'
    ];

    public function tests(){
        return $this->belongsTo('App\Models\Test', 'test_id');
    }

    public function student(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }
}
