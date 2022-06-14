<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Religion extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'slug',
    ];

    public function family(){
        return $this->hasOne('App\Models\Family');
    }

    public function student(){
        return $this->hasOne('App\Models\Student');
    }

    public function teacher(){
        return $this->hasOne('App\Models\Teacher');
    }
}
