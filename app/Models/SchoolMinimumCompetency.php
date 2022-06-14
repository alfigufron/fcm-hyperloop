<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolMinimumCompetency extends Model
{
    protected $table = 'school_minimum_competencies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id', 'value',
    ];

    public function school(){
        return $this->belongsTo('App\Models\School', 'school_id');
    }
}
