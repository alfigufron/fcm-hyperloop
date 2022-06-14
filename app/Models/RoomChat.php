<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomChat extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'room_type'
    ];

    /**
     * Relation
     */

    public function roomchat_member() {
        return $this->hasMany('App\Models\RoomChatMember');
    }

    public function chat() {
        return $this->hasMany('App\Models\Chat');
    }
}
