<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolAdmin extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'admin_type',
    ];

    public function user(){
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function school(){
        return $this->belongsTo('App\Models\School', 'school_id');
    }
}
