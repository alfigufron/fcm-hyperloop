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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TeacherLessonController extends Controller
{
    public function next(){
        // Get Teacher id by Auth
        $user = Auth::user();
        $teacher = $user->teacher;

        // Date & Time now
        $dateNow = Carbon::now('Asia/Jakarta')->toDateString();
        $timeNow = Carbon::now('Asia/Jakarta')->toTimeString();

        // Teacher Classroom
        $teacherClassroom = $teacher->teacher_classrooms()->where('classroom_type', 'regular');

        // Querying and Sorting next lesson meet
        $schedule = Schedule::query()
            ->select(DB::raw('min(id) as id'), DB::raw('min(subject_id) as subject_id'),
                        DB::raw('min(classroom_id) as classroom_id'), DB::raw('min(teacher_id) as teacher_id'), 'date',
                        DB::raw('min(start_at) as start_at'), DB::raw('max(end_at) as end_at'))
            ->where('teacher_id', $teacher->id)
            ->where('date', '>=' ,$dateNow)
            ->whereIn('classroom_id', $teacherClassroom->pluck('classroom_id'))
            ->orderBy('date')->orderBy('start_at')
            ->groupBy('date');

        // Validate
        if ($schedule->get()->isEmpty()){
            return Response::status('failure')->result(["Unexpected error"]);
        }

        // Get Schedule
        if($schedule->first()->date == $dateNow){
            if ($schedule->first()->end_at >= $timeNow) {
                $todaySch = $schedule->where('date', '=', $dateNow)
//                ->where('start_at', '<=', $timeNow)
                    ->where('end_at', '>=', $timeNow)
                    ->first();
            }
            else{
                $todaySch = $schedule->where('date', '>', $dateNow)->first();
            }
        }
        else{
            $todaySch = $schedule->where('date', '>', $dateNow)->first();
        }

        // Check school or dormitory
        if($todaySch->classrooms->schools()->exists()){
            $schoolOrDorm = 'Classroom ';
        }
        elseif ($todaySch->classrooms->dormitories()->exists()){
            $schoolOrDorm = "Dorm Room ";
        }

        // Check if classroom has major or not
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
                'start_at' =>  Carbon::parse($todaySch->start_at)->format('G:i'),
                'end_at' => Carbon::parse($todaySch->end_at)->format('G:i'),
                'day' => Carbon::parse($todaySch->date)->format('l'),
                'date' => Carbon::parse($todaySch->date)->format('d F Y'),
            ]
        ];

        return Response::status('success')->result($data);
    }

    public function list(Request $request){
        // prerequisites
        $user = Auth::user();
        $teacher = $user->teacher;
        $classroomId = $request->classroom_id;
        if($user->teacher->schools()->exists()){
            $school = $user->teacher->schools()->first();
        }
        elseif ($user->teacher->dormitories()->exists()){
            $dormitory =  $user->teacher->dormitories()->first();
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|numeric',
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

        //Querying
        $classroom = Classroom::find($classroomId);

        //Validate Classroom
        if ($classroom == null){
            return Response::status('failure')->code(204)->result(["Couldn't find classroom"]);
        }

        // Get School Year
        foreach ($classroom->students as $student){
            $true = $student->pivot->where('is_active', true)->first();
            $year = $true->school_year;
        }

        $classSubject = Schedule::query()
            ->whereNotNull('classroom_id')
            ->select(DB::raw('min(teacher_id) as teacher_id'), DB::raw('min(classroom_id) as classroom_id'), 'subject_id')
            ->where('teacher_id', $teacher->id)
            ->where('classroom_id', $classroomId)
            ->where('school_year', $year)
            ->with('subject')
            ->groupBy('subject_id')
            ->get();

        $lessonSubject = Lesson::select(DB::raw('min(id) as id'),DB::raw('min(teacher_id) as teacher_id'), DB::raw('min(classroom_id) as classroom_id'), 'subject_id')
            ->where('teacher_id', $teacher->id)
            ->where('classroom_id', $classroom->id)
            ->whereNull('title')
            ->where('school_year', $year)
            ->groupBy('subject_id')
            ->get();

        // Foreach
        $records = [];
        $id = 1;
        if (count($classSubject) > 0) {
            foreach ($classSubject as $item) {
                $subject = $item->subjects;

                // Task and Test to check
                $tasks = Assignment::with('student_assignments')->where('subject_id', $subject->id)->where('classroom_id', $classroomId)->get();
                $workTask = 0;
                foreach ($tasks as $task) {
                    $arg = $task->student_assignments->where('score', '===', null)->first();
                    if ($arg != null) {
                        $workTask++;
                    }
                }

                $tests = Test::with('student_tests')->where('subject_id', $subject->id)->where('classroom_id', $classroomId)->get();
                $workTest = 0;
                foreach ($tests as $test) {
                    $arg = $test->student_tests->where('score', '===', null)->first();
                    if ($arg != null) {
                        $workTest++;
                    }
                }

                if ($subject->dormitories()->exists()) {
                    $colors = $subject->dormitories()->wherePivot('dormitory_id', $dormitory->id)->first();
                } elseif ($subject->schools()->exists()) {
                    $colors = $subject->schools()->wherePivot('school_id', $school->id)->first();
                }
                $dataClassSubject = [
                    'id' => $id++,
                    'lesson_id' => null,
                    'teacher_id' => $teacher->id,
                    'teacher' => $teacher->name,
                    'subject_id' => $item->subject_id,
                    'subject' => $subject->name,
                    'subject_code' => $subject->code,
                    'grade' => $classroom->grade,
                    'color' => $colors->pivot->color,
                    'type' => 'regular',
                    'work_to_check' => [
                        'task' => $workTask,
                        'test' => $workTest,
                    ]
                ];

                array_push($records, $dataClassSubject);
            }
        }

        if (count($lessonSubject) > 0) {
            foreach ($lessonSubject as $item) {
                $subject = $item->subject;

                // Get Work Task & Test to check
                $workTask = 0;
                $workTest = 0;

                // Get Color
                if ($subject->dormitories()->exists()) {
                    $colors = $subject->dormitories()->wherePivot('dormitory_id', $dormitory->id)->first();
                } elseif ($subject->schools()->exists()) {
                    $colors = $subject->schools()->wherePivot('school_id', $school->id)->first();
                }
                $dataLessonSubject = [
                    'id' => $id++,
                    'lesson_id' => $item->id,
                    'teacher_id' => $teacher->id,
                    'teacher' => $teacher->name,
                    'subject_id' => $item->subject_id,
                    'subject' => $subject->name,
                    'subject_code' => $item->subject->code,
                    'grade' => $classroom->grade,
                    'color' => $colors->pivot->color,
                    'type' => 'additional',
                    'work_to_check' => [
                        'task' => $workTask,
                        'test' => $workTest,
                    ]
                ];
                array_push($records, $dataLessonSubject);
            }
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

    public function detail(Request $request){
        $lessonId = $request->input('lesson_id');

        $lesson = Lesson::with(['classroom' => function($query){
                $query->with('students');
            }])
            ->with(['students' => function($query){
                $query->wherePivot('deleted_at', '=', null);
            }])
            ->where('id', $lessonId)
            ->first();

        foreach ($lesson->students as $student){
            $lessonstudent = [];
            $data[] = [
                'student_id' => $student->id,
                'name' => $student->name,
                'is_additional' => $student->pivot->is_additional
            ];
            array_push($lessonstudent, $data);
        }

        if ($lesson->subject->has('schools')){
            $idschool = $lesson->subject->schools()->first();
        }
        elseif ($lesson->subject->has('dormitory')){
            $idschool = $lesson->subject->dormitories()->first();
        }

        $result = [
            'lesson_id' => $lesson->id,
            'subject' => $lesson->subject->name,
            'subject_id' => $lesson->subject_id,
            'color' => $idschool->pivot->color,
            'students' => $lessonstudent
        ];

        dump($result);

        return Response::status('success')->code(200)->result($result);
    }

    public function selectSubjects(){
        $user = Auth::user();
        $teacher = $user->teacher;

        $teacherSubject = $teacher->subjects;

        //Foreach
        $subject = [];
        foreach ($teacherSubject as $item){
            $dataSubject = [
                'subject_id' => $item->id,
                'name' => $item->name
            ];
            array_push($subject, $dataSubject);
        }

        $result = [
            'subject' =>$subject,
        ];

        return Response::status('success')->result($result);
    }

    public function additionalStudent(Request $request){
        $classroomId = $request->input('classroom_id');
        $user = Auth::user();
        $teacher = $user->teacher;

        // School Year
        $classroom = Classroom::with('students')->where('id', $classroomId);
        $active = $classroom->first()->students()->wherePivot('is_active', true)->first();
        $year = $active->pivot->school_year;

        $classrooms = $teacher->teacher_classrooms()->where('classroom_type', 'regular')->where('classroom_id', '!=', $classroomId);

        $students = [];
        foreach ($classrooms->get() as $item){
            foreach ($item->students->where('pivot.is_active', true) as $student){
                $additionalStudent = [
                    'student_id' => $student->id,
                    'name' => $student->name
                ];
                array_push($students, $additionalStudent);
            }
        }
        return Response::status('success')->result($students);
    }

    public function create(Request $request){
        // Prerequisites
        $teacher = Auth::user();
        $teacherId = $teacher->teacher->id;
        $subjectId = $request->input('subject_id');
        $classroomId = $request->input('classroom_id');
        $students = $request->input('student_id');

        // Validate

        //School Year
        $classroom = Classroom::find($classroomId);
        foreach ($classroom->students as $student){
            $true = $student->pivot->where('is_active', true)->where('classroom_id', $classroomId)->first();
            $year = $true->school_year;
        }

        //insert new lesson
        $newlesson = new Lesson;
        $newlesson->subject_id = $subjectId;
        $newlesson->classroom_id = $classroomId;
        $newlesson->teacher_id = $teacherId;
        $newlesson->lesson_type = 'additional';
        $newlesson->school_year = $year;
        $newlesson->save();

//        // Insert new student to lesson_student
//        $lesson = Lesson::find($newlesson->id);
//        $classroom = Classroom::find($classroomId);
//        foreach ($students as $s){
//            $has_student = $classroom->students()->where('student_id', $s)->where('is_active', true)->exists();
//            if ($has_student == false){
//                $additional[$s] = ['is_additional' => true];
//            }
//            elseif($has_student == true){
//                $additional[$s] = ['is_additional' => false];
//            }
//        }
//        $lesson->students()->attach($additional);


//        // Insert new student to the classroom
//        $status = ['is_leader' => false, 'is_active' => true];
//        $classroom = Classroom::find($classroomid);
//        foreach ($students as $id) {
//            $student[$id] = $status;
//        }
//        $classroom->students()->syncWithoutDetaching($student);

        return Response::status('success')->code(201)->result(null);
    }

    /**
     * TASK
     *
     */
    public function task(Request $request){
        $classroomId = $request->query('classroom_id');
        $page = $request->query('page') ?? 1;

        $assignments = Assignment::where('classroom_id', $classroomId)->with(['classroom', 'subject'])->paginate(10);
        $items = $assignments->items();

        // Pagination
        $pagination = [
            'total_page' => $assignments->lastPage(),
            'total_records' => $assignments->total(),
            'current_page' => $assignments->currentPage(),
        ];

        // Records
        $records = [];
        foreach($items as $key => $item){
            $dataPush = [
                'subject' => $item->subject->name,
                'grade' => $item->classroom->grade,
                'lesson' => $item->title,
                'deadline' => [
                    'date' => Carbon::parse($item->deadline)->format('l, d F Y')
                ]
            ];

            array_push($records, $dataPush);
        }

        $result = [
            'pagination' => $pagination,
            'records' => $records,
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * TEST
     *
     */
    public function test(Request $request){
        $classroomId = $request->query('classroom_id');
        $page = $request->query('page') ?? 1;

        $tests = Test::where('classroom_id', $classroomId)->with(['classroom', 'subject'])->paginate(1);
        $items = $tests->items();

        // Pagination
        $pagination = [
            'total_page' => $tests->lastPage(),
            'total_records' => $tests->total(),
            'current_page' => $tests->currentPage(),
        ];

        // Records
        $records = [];
        foreach($items as $key => $item){
            $dataPush = [
                'subject' => $item->subject->name,
                'grade' => $item->classroom->grade,
                'lesson' => $item->title,
                'deadline' => [
                    'date' => Carbon::parse($item->end_at)->format('l, d F Y')
                ]
            ];

            array_push($records, $dataPush);
        }

        $result = [
            'pagination' => $pagination,
            'records' => $records,
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * UPDATE LESSON
     *
     */
    public function update(Request $request){
        $user = Auth::user();
        $teacherId = $user->teacher->id;
        $lessonId = $request->input('lesson_id');
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');
        $studentId = $request->input('student_id');

        // Query
        $lesson = Lesson::with(['lesson_medias', 'students' => function($query){
                $query->wherePivot('deleted_at', '=', null);
            }])
            ->where('id', $lessonId)->where('teacher_id', $teacherId)
            ->whereNull('deleted_at')
            ->first();

        // Validate
        if ($lesson == null){
            return Response::status('failure')->result(["Couldn't find lesson"]);
        }

        // Insert and Delete Student
        $lesson->students()->sync($studentId);

        // ReInsert Student
        foreach ($studentId as $id){
            $exists = $lesson->students()->wherePivot('student_id', $id)->exists();
            if ($exists == true){
                $lesson->students()->updateExistingPivot($id, ['deleted_at' => null]);
            }
        }

        return Response::status('success')->result([]);
    }

    /**
     * DELETE LESSON
     *
     */
    public function delete(Request $request){
        $user = Auth::user();
        $teacher = $user->teacher;

        $lesson = Lesson::where('id', $request->id)->where('teacher_id', $teacher->id)->with(['lesson_medias', 'student_lessons'])->first();
        if(!$lesson){
            return Response::status('failure')->code(422)->result([]);
        }

        $lesson->deleteWithAllRelatedData();

        return Response::status('success')->code(200)->result([]);
    }
}
