<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningContractInformation extends Model
{
    protected $table = 'learning_contract_informations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'learning_contract_information_id', 'week', 'session', 'hour', 'basic_competition', 'main_topic', 'sub_topic', 'category'
    ];

    public function learning_contract(){
        return $this->belongsTo('App\Models\LearningContract', 'learning_contract_id');
    }

    public function schedules(){
        return $this->hasMany('App\Models\Schedule');
    }

    public function assignments(){
        return $this->hasMany('App\Models\Assignment');
    }

    public function tests(){
        return $this->hasMany('App\Models\Test');
    }

    public function lessons(){
        return $this->hasMany('App\Models\Lesson');
    }
}
