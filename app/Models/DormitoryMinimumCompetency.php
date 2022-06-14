<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DormitoryMinimumCompetency extends Model
{
    protected $table = 'dormitory_minimum_competencies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'dormitory_id', 'value',
    ];

    public function dormitory(){
        return $this->belongsTo('App\Models\Dormitory', 'dormitory_id');
    }
}
