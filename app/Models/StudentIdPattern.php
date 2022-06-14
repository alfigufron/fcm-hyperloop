<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentIdPattern extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code', 'name', 'slug', 'start_index', 'end_index', 'value', 'status'
    ];
}
