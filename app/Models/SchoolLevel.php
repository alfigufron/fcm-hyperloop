<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolLevel extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'slug',
    ];

    public function schools(){
        return $this->hasMany('App\Models\School');
    }
}
