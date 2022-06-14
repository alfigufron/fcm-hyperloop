<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormulaParameter extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'formula_parameters';
    
    protected $fillable = [
        'formula_id', 'parameter', 'weight'
    ];

    public function formula_parameter_components(){
        return $this->hasMany('App\Models\FormulaParameterComponent');
    }
}
