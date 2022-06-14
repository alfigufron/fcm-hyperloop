<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'slug'
    ];

    public function school(){
        return $this->hasMany('App\Models\School');
    }
}
