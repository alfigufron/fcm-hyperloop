<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;


class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username', 'email', 'password_digest', 'role_id', 'web_token', 'api_token', 'fcm_token'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getAuthPassword()
    {
        return $this->password_digest;
    }

    /**
     * Relation
     */

    public function dormitories(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_admins')
            ->using('App\Models\DormitoryAdmin');
    }
    public function schools(){
        return $this->belongsToMany('App\Models\School', 'school_admins')
            ->using('App\Models\SchoolAdmin')
            ->withPivot('admin_type');
    }

    public function family(){
        return $this->hasOne('App\Models\Family');
    }

    public function device(){
        return $this->hasOne('App\Models\UserDevice');
    }

    public function passwordreset(){
        return $this->hasOne('App\Models\PasswordReset');
    }

    public function student(){
        return $this->hasOne('App\Models\Student');
    }

    public function role(){
        return $this->belongsTo('App\Models\Role');
    }

    public function teacher(){
        return $this->hasOne('App\Models\Teacher');
    }

    public function school_admin(){
        return $this->hasOne('App\Models\SchoolAdmin');
    }

    public function dormitory_admin(){
        return $this->hasOne('App\Models\DormitoryAdmin');
    }

    public function roomchat_member() {
        return $this->hasMany('App\Models\RoomChatMember');
    }

    public function chat() {
        return $this->hasMany('App\Models\Chat', 'sender_id', 'id');
    }
}
