<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDetail extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date_of_birth', 'address', 'phone', 'gender', 'profile_picture',
    ];

    public function student(){
        return $this->belongsTo('App\Models\Student');
    }

    public function religion(){
        return $this->belongsTo('App\Models\Religion');
    }

    public function city(){
        return $this->belongsTo('App\Models\City');
    }
}
