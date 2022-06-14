<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFamily extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'relationship_role', 'is_recomend',
    ];

    public function student(){
        return $this->belongsTo('App\Models\Student', 'student_id');
    }

    public function family(){
        return $this->belongsTo('App\Models\Family', 'family_id');
    }
}
