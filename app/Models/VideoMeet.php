<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoMeet extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'schedule_id', 'link', 'start_at', 'finish_at'
    ];

    public function schedule(){
        return $this->belongsTo('App\Models\Schedule', 'schedule_id');
    }

    public function video_meet_participants(){
        return $this->hasMany('App\Models\VideoMeetParticipant');
    }
}
