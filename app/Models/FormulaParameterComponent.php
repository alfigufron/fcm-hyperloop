<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormulaParameterComponent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'formula_parameter_components';
    
    protected $fillable = [
        'formula_parameter_id', 'component', 'weight'
    ];

}
