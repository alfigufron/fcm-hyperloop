<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DormitoryLeftPermission extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'description' ,'from_date', 'to_date', 'photo', 'status', 'has_return',
    ];

    public function students(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }

    public function dormitories(){
        return $this->belongsTo('App\Models\Dormitory', 'dormitory_id');
    }
}
