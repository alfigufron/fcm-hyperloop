<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgendaCategory extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'slug', 'color',
    ];

    public function agendas(){
        return $this->belongsToMany('App\Models\Agenda', 'agenda_category_pivots')
            ->using('App\Models\AgendaCategoryPivot');
    }
}
