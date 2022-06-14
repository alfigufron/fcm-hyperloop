<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'device_id', 'last_login_at',
    ];

    public function device_user(){
        return $this->belongsTo('App\Models\user');
    }
}
