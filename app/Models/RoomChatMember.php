<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomChatMember extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'is_admin',
        'user_id',
        'room_chat_id'
    ];

    /**
     * Relation
     */

    public function user() {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function roomchat() {
        return $this->belongsTo('App\Models\RoomChat', 'room_chat_id');
    }
}
