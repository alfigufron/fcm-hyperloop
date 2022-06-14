<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAchievement extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'description', 'predicate', 'date', 'organizer'
    ];

    public function student(){
        return $this->belongsToMany('App\Models\Student', 'student_achievement_teams')
            ->using('App\Models\StudentAchievementTeam');
    }
}
