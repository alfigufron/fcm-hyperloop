<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'description', 'image', 'is_active',
    ];

    public function dormitories(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_announcements')
            ->using('App\Models\DormitoryAnnouncement');
    }

    public function schools(){
        return $this->belongsToMany('App\Models\School', 'school_announcements')
            ->using('App\Models\SchoolAnnouncement');
    }
}
