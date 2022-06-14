<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agenda extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'start', 'end', 'description',
    ];

    public function agendacategories(){
        return $this->belongsToMany('App\Models\AgendaCategory', 'agenda_category_pivots')
            ->using('App\Models\AgendaCategoryPivot');
    }

    public function dormitories(){
        return $this->belongsToMany('App\Models\Dormitory', 'dormitory_agendas')
            ->using('App\Models\DormitoryAgendas');
    }

    public function medias(){
        return $this->belongsToMany('App\Models\Media', 'agenda_medias')
            ->using('App\Models\AgendaMedias')
            ->withPivot('date', 'description');
    }

    public function schools(){
        return $this->belongsToMany('App\Models\School', 'school_agendas')
            ->using('App\Models\SchoolAgendas');
    }
}
