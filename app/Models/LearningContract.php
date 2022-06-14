<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningContract extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subject_id', 'teacher_id', 'grade', 'semester', 'school_year', 'is_used',
    ];

    public function dormitories(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_learning_contracts')
            ->using('App\Models\DormitoryLearningContract');
    }

    public function schools(){
        return $this->belongsToMany('App\Models\School', 'school_learning_contracts')
            ->using('App\Models\SchoolLearningContract');
    }

    public function learning_contract_informations(){
        return $this->hasMany('App\Models\LearningContractInformation');
    }

    public function formulas(){
        return $this->hasMany('App\Models\Formula','learning_contract_id');
    }

    public function teachers(){
        return $this->belongsTo('App\Models\Teacher');
    }

    public function subject(){
        return $this->belongsTo('App\Models\Subject');
    }
}
