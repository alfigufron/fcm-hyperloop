<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DormitoryAdmin extends Model
{
    protected $table = 'dormitory_admins';

    protected $fillable = ['name'];

    public function user(){
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function dormitory(){
        return $this->belongsTo('App\Models\Dormitory', 'dormitory_id');
    }
}
