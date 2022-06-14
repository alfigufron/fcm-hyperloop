<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chat extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'message',
        'name',
        'sender_id',
        'room_chat_id'
    ];

    /**
     * Relation
     */

    public function user() {
        return $this->belongsTo('App\Models\User', 'sender_id', 'user_id');
    }

    public function roomchat() {
        return $this->belongsTo('App\Models\RoomChat');
    }
}
