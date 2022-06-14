<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Media extends Model
{

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'media';

    protected $fillable = [
        'name', 'file', 'type', 'category',
    ];

    public function agendas(){
        return $this->belongsToMany('App\Models\Agenda', 'agenda_medias')
            ->using('App\Models\AgendaMedia')
            ->withPivot('date', 'description');
    }

    public function assignments(){
        return $this->belongsToMany('App\Models\Assignment', 'assignment_media')
            ->using('App\Models\AssignmentMedia');
    }

    public function lesson(){
        return $this->belongsToMany('App\Models\Lesson', 'lesson_medias')
            ->using('App\Models\LessonMedia');
    }

    public function questionitemanswers(){
        return $this->belongsToMany('App\Models\QuestionItemAnswer', 'question_item_answer_medias')
            ->using('App\Models\QuestionItemAnswerMedia');
    }

    public function questiondiscusses(){
        return $this->belongsToMany('App\Models\QuestionItemDiscuss', 'question_item_discusses_medias')
            ->using('App\Models\QuestionItemDiscussesMedia');
    }

    public function questionitems(){
        return $this->belongsToMany('App\Models\QuestionItem', 'question_item_medias')
            ->using('App\Models\QuestionItemMedia');
    }

    public function schedules(){
        return $this->belongsToMany('App\Models\Schedule', 'schedule_media')
            ->using('App\Models\ScheduleMedia');
    }

    public function studentassignments(){
        return $this->belongsToMany('App\Models\StudentAssignment', 'student_assignment_media')
            ->using('App\Models\StudentAssignmentMedia');
    }

    public function studentdocuments(){
        return $this->belongsToMany('App\Models\Student', 'student_documents')
            ->using('App\Models\StudentDocument');
    }
}
