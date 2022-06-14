<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DormitoryRoom extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code', 'capacity', 'used_capacity',
    ];

    public function dormitories(){
        return $this->belongsTo('App\Models\Dormitory');
    }
}
