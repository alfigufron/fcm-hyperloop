<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\Test;
use App\Utils\Paginate;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentLessonController extends Controller
{
    /**
     * LIST
     *
     */
    // public function list(Request $request){
    //     $classroomId = $request->query('classroom_id');
    //     $user = Auth::user();
    //     $schoolId = $user->student->current_classroom()->school[0]->id;

    //     $schedules = Schedule::select('subject_id', DB::raw('max(teacher_id) AS teacher_id'), DB::raw('max(classroom_id) AS classroom_id'))->where('classroom_id', $classroomId)
    //     ->with(['classroom', 'teacher'])
    //     ->with(['subject' => function($q) use($user, $schoolId){
    //         $q->with(['school_subjects' => function($q) use($user, $schoolId){
    //             $q->where('school_id', $schoolId);
    //         }]);
    //     }])->groupBy('subject_id')->paginate(10);

    //     $items = $schedules->items();

    //     // Pagination
    //     $pagination = [
    //         'total_page' => $schedules->lastPage(),
    //         'total_records' => $schedules->total(),
    //         'current_page' => $schedules->currentPage(),
    //     ];

    //     // Result
    //     $records = [];
    //     foreach($items as $item){
    //         $dataPush = [
    //             'classroom_id' => $item->classroom_id,
    //             'teacher_id' => $item->teacher->id,
    //             'teacher' => $item->teacher->name,
    //             'subject_id' => $item->subject->id,
    //             'subject' => $item->subject->name,
    //             'subject_code' => $item->subject->code,
    //             'grade' => 'Grade '.$item->classroom->grade,
    //             'color' => $item->subject->school_subjects[0]->color,
    //             'type' => 'regular'
    //         ];

    //         array_push($records, $dataPush);
    //     }

    //     $result = [
    //         'pagination' => $pagination,
    //         'records' => $records,
    //     ];

    //     return Response::status('success')->code(200)->result($result);
    // }

    public function list(Request $request) {
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|numeric|exists:classrooms,id',
        ]);

        if ($validator->fails()){
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message) {
                $dataPush = $message;
                array_push($error, $dataPush);
            }

            return Response::status('failure')->code(401)->result($error);
        }

        $user = Auth::user();
        $student = $user->student;

        $classroom_id = $request->classroom_id;
        $classroom = Classroom::find($classroom_id);

        if ($student->schools()->exists())
            $school = $student->schools()->first();

        if ($student->dormitories()->exists())
            $dormitory = $student->schools()->first();

        foreach ($classroom->students as $student) {
            $active = $student->pivot->where('is_active', true)->first();
            if ($active) {
                $year = $active->school_year;
                break;
            }
        }

        $class_subjects = Schedule::select(DB::raw('min(teacher_id) as teacher_id'), DB::raw('min(classroom_id) as classroom_id'), 'subject_id')
            ->where('classroom_id', $classroom_id)
            ->where('school_year', $year)
            ->with('subject')
            ->groupBy('subject_id')
            ->get();

        $lesson_subjects = Lesson::select(DB::raw('min(id) as id'),DB::raw('min(teacher_id) as teacher_id'), DB::raw('min(classroom_id) as classroom_id'), 'subject_id')
            ->where('classroom_id', $classroom->id)
            ->where('school_year', $year)
            ->groupBy('subject_id')
            ->get();

        $records = [];
        $id = 1;

        if (count($class_subjects) > 0)
            foreach ($class_subjects as $item) {
                $subject = $item->subject;

                $tasks = Assignment::with('student_assignments')
                    ->where('subject_id', $subject->id)
                    ->where('classroom_id', $classroom_id)
                    ->get();

                $work_task = 0;
                foreach ($tasks as $task) {
                    $arg = $task->student_assignments->where('score', '===', null)->first();
                    if ($arg != null)
                        $work_task++;
                }

                $tests = Test::with('student_tests')
                    ->where('subject_id', $subject->id)
                    ->where('classroom_id', $classroom_id)
                    ->get();

                $work_test = 0;
                foreach ($tests as $test) {
                    $test->student_tests->where('score', '===', null)->first();
                    if ($arg != null)
                        $work_test++;
                }

                if ($subject->dormitories()->exists())
                    $colors = $subject->dormitories()
                        ->wherePivot('dormitory_id', $dormitory->id)
                        ->first();

                if ($subject->schools()->exists())
                    $colors = $subject->schools()
                        ->wherePivot('school_id', $school->id)
                        ->first();

                $data_class_subject = [
                    'id' => $id++,
                    'lesson_id' => null,
                    'teacher_id' => $item->teacher_id,
                    'teacher' => $item->teacher->name,
                    'subject_id' => $item->subject_id,
                    'subject' => $subject->name,
                    'subject_code' => $subject->code,
                    'grade' => $classroom->grade,
                    'color' => $colors->pivot->color,
                    'type' => 'regular',
                    'work_to_check' => [
                        'task' => $work_task,
                        'test' => $work_test,
                    ]
                ];

                array_push($records, $data_class_subject);
            }

        if (count($lesson_subjects) > 0)
            foreach ($lesson_subjects as $item) {
                $subject = $item->subject;

                $work_task = 0;
                $work_test = 0;

                if ($subject->dormitories()->exists())
                    $colors = $subject->dormitories()
                        ->wherePivot('dormitory_id', $dormitory->id)
                        ->first();

                if ($subject->schools()->exists())
                    $colors = $subject->schools()
                        ->wherePivot('school_id', $school->id)
                        ->first();

                $data_lesson_subject = [
                    'id' => $id++,
                    'lesson_id' => $item->id,
                    'teacher_id' => $item->teacher_id,
                    'teacher' => $item->teacher->name,
                    'subject_id' => $item->subject_id,
                    'subject' => $subject->name,
                    'subject_code' => $item->subject->code,
                    'grade' => $classroom->grade,
                    'color' => $colors->pivot->color,
                    'type' => 'additional',
                    'work_to_check' => [
                        'task' => $work_task,
                        'test' => $work_test,
                    ]
                ];

                array_push($records, $data_lesson_subject);
            }

        $page = Paginate::paginator($records);

        $result = [
            'paginate' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage()
            ],
            'records' => $page->items()
        ];

        return Response::status('success')->code(200)->result($result);
    }

    public function next(){
        // Prerequisites
        $user = Auth::user();
        $student = $user->student;
        $dateNow = Carbon::now()->toDateString();
        $timeNow = Carbon::now()->toTimeString();

        // Classroom
//        $classrooms = $student->classrooms()->wherePivot('is_active', true)->whereIn('classroom_type', ['regular', 'replacement'])->get();
        $classrooms = $student->classrooms()->wherePivot('is_active', true)->where('classroom_type', 'regular')->orderBy('id')->get();

        // Schedules
        $rawSchedules = Schedule::query()
            ->select(DB::raw('min(id) as id'), 'subject_id',
                       'classroom_id', 'teacher_id', 'date', DB::raw('min(start_at) as start_at'),
                        DB::raw('max(end_at) as end_at'))
            ->whereIn('classroom_id', $classrooms->pluck('id'))
            ->where('date', '>=', $dateNow)
            ->groupBy('subject_id', 'classroom_id' ,'teacher_id', 'date')
            ->orderBy('date')
            ->orderBy('start_at')
//            ->get()
            ;

        // Validate Schedule
        if (!$rawSchedules){
            return Response::status('failure')->result("no more agendas");
        }

        // Determine today has schedule or not
        if ($rawSchedules->first()->date == $dateNow) {
            if ($rawSchedules->first()->end_at >= $timeNow) {
                $todaySch = $rawSchedules->where('date', '=', $dateNow)
//                    ->where('start_at', '<=', $timeNow)
                    ->where('end_at', '>=', $timeNow)
                    ->first();
            }
            else{
                $todaySch = $rawSchedules->where('date', '>', $dateNow)->first();
            }
        }

        else {
            $todaySch = $rawSchedules->where('date', '>', $dateNow)->first();
        }

        // Determine is school or dormitory
        if ($todaySch->classrooms->schools()->exists()){
            $schoolOrDorm = 'Classroom ';
        }
        elseif ($todaySch->classrooms->schools()->exists()){
            $schoolOrDorm = 'Dorm Room ';
        }

        // Check if has major or not
        if ($todaySch->classrooms->major->slug != 'general'){
            $major = " (".strtoupper($todaySch->classrooms->major->slug).")";

            $className = $schoolOrDorm.$todaySch->classrooms->name.$major;
        }
        else{
            $className = $schoolOrDorm.$todaySch->classrooms->name;
        }

        $data = [
            'classroom' => $className,
            'subject' => $todaySch->subjects->name,
            'grade' => $todaySch->classrooms->grade,
            'schedule' => [
                'start_at' => Carbon::parse($todaySch->start_at)->format('G:i'),
                'end_at' => Carbon::parse($todaySch->end_at)->format('G:i'),
                'day' => Carbon::parse($todaySch->date)->format('l'),
                'date' => Carbon::parse($todaySch->date)->format('d F Y'),
            ]
        ];

        return Response::status('success')->result($data);
    }
}
