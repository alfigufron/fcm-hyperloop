<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Subject;
use App\Utils\Paginate;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherForumClassReplacementController extends Controller
{
    public function header(Request $request){
        $subjectId = $request->subject_id;
        $grade = $request->query('grade');
        $semester = $request->query('semester');

        if($subjectId == null){
            return Response::status('failure')->result(['subject_id is required']);
        }

        $subject = Subject::find($subjectId);

        $result = [
            'first' => $subject->name,
            'second' => [
                'grade' => $grade,
                'semester' => $semester
            ]
        ];

        return Response::status('success')->result($result);
    }

    public function detail(Request $request){
        $user = Auth::user();
        $teacher = $user->teacher;
        $semester = $request->input('semester');
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');

        $oldClass = Classroom::where('id', $classroomId)->first();
        $year = $oldClass->students()->wherePivot('is_active', true)->first()->pivot->school_year;

        $classrooms = Classroom::query()
            ->with('students')
            ->with('schedules')
            ->whereHas('schedules', function ($query) use($subjectId, $semester, $year){
                $query->where('schedules.schedule_type', 'once');
                $query->where('schedules.semester', $semester);
                $query->where('schedules.subject_id', $subjectId);
                $query->where('schedules.school_year', $year);
            })
            ->where('teacher_id', $teacher->id)
            ->where('classroom_type', 'replacement')
            ->where('grade', $oldClass->grade)
            ->get();

        $record = [];
        $i = 1;
        foreach ($classrooms as $classroom){
            $students = [];
            foreach ($classroom->students as $student){
                $dataStudent = [
                    'student_id' => $student->id,
                    'name' => $student->name
                ];
                array_push($students, $dataStudent);
            }

            $schedules = [];
            foreach ($classroom->schedules as $schedule){
                $dataSchedule = [
                    'date' => $schedule->date,
                    'day' => Carbon::parse($schedule->date)->format('l'),
                    'start_at' => $schedule->start_at,
                    'end_at' => $schedule->end_at,
                    'main_topic' => $schedule->learning_contract_information->main_topic,
                    'sub_topic' => $schedule->learning_contract_information->sub_topic,
                ];
                array_push($schedules, $dataSchedule);
            }

            $dataRecord = [
                'id' => $i++,
                'students' => $students,
                'schedule' => collect($schedules)->first()
            ];

            array_push($record, $dataRecord);
        }

        $page = Paginate::paginator($record);

        $result = [
            'paginate' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage()
            ],
            'records' => $page->items()
        ];

        return Response::status('success')->result($result);
    }
}
