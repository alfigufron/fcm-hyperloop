<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    public function families(){
        return $this->hasOne('App\Models\Family');
    }

    public function studentdetails(){
        return $this->hasOne('App\Models\StudentDetail');
    }

    public function teachers(){
        return $this->hasOne('App\Models\Teacher');
    }
}
