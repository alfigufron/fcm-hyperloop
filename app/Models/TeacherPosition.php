<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherPosition extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'slug',
    ];

    public function position_teacher(){
        return $this->hasOne('App\Models\Teacher');
    }
}
