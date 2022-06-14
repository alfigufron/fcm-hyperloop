<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class StudentMedicalRecord extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code', 'disease', 'description', 'is_infectable', 'recovery_site', 'date',
    ];

    public function medical_record_student(){
        return $this->belongsTo('App\Models\Student');
    }
}
