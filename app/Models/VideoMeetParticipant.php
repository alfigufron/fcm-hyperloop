<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoMeetParticipant extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'video_meet_id', 'user_id', 'link', 'start_at', 'finish_at'
    ];

    public function video_meet(){
        return $this->belongsTo('App\Models\VideoMeet', 'video_meet_id');
    }

    public function user(){
        return $this->belongsTo('App\Models\User');
    }
}
