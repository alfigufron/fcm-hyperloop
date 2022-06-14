<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'families';
    
    protected $fillable = [
        'identity_number', 'name', 'date_of_birth', 'phone', 'email', 'address', 'gender', 'profile_picture'
    ];

    public function students(){
        return $this->belongsToMany('App\Models\Student', 'student_families')
            ->using('App\Models\StudentFamily');
    }

    public function student_families(){
        return $this->hasMany('App\Models\StudentFamily');
    }

    public function user(){
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function job(){
        return $this->belongsTo('App\Models\Job', 'job_id');
    }

    public function city(){
        return $this->belongsTo('App\Models\City', 'city_id');
    }

    public function religion(){
        return $this->belongsTo('App\Models\Religion', 'religion_id');
    }
}
