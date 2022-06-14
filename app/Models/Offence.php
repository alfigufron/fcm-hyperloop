<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offence extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'offence_type', 'penalty','description', 'date', 'file',
    ];

    public function students(){
        return $this->belongsToMany('App\Models\Student', 'student_offences')
            ->using('App\Models\StudentOffence');
    }
}
