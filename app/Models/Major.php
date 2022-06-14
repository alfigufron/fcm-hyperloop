<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Major extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'slug', 'name',
    ];

    public function classroom(){
        return $this->hasOne('App\Models\Classroom');
    }

    public function subject(){
        return $this->hasOne('App\Models\Subject');
    }
}
