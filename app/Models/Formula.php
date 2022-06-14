<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Formula extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    
    protected $fillable = [
        'learning_contract_id', 'basic_competency', 'cognitive_type', 'status'
    ];

    public function formula_parameter(){
        return $this->belongsTo('App\Models\FormulaParameter','id','formula_id');
    }
}
