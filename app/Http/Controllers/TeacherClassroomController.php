<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Utils\Paginate;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TeacherClassroomController extends Controller
{
    public function index(Request $request){
        $user = Auth::user();
        $teacher = $user->teacher;

        // Validating query param
        if (! in_array($request->type, ['regular', 'general', 'replacement'])){
            return Response::Status('failure')->Code(422)->result(['Unexpected type value']);
        }

        // Query
        $userTeacher = Teacher::query()
            ->with(['teacher_classrooms' => function($query){
                $query->orderBy('school_year', 'desc');
            }])
            ->where('id', $teacher->id)
            ->first();
        $rawQuery = $userTeacher->teacher_classrooms;
        $year = $rawQuery->first()->pivot->school_year;

        // Determine request type value
        if ($request->type == 'general') {
            $classrooms = $rawQuery->wherein('classroom_type', ['regular', 'extra', 'matriculation'])->where('pivot.school_year', $year);
        }
        else{
            $classrooms = $rawQuery->where('classroom_type', 'replacement')->where('pivot.school_year', $year);
        }

        // Determine request paginate value
        $page = Paginate::paginator($classrooms);
        $items = $page->items();

        // Foreach Data
        $record = [];
        foreach ($items as $item){
            // School Classrooms
            if ($item->schools()->exists()){
                $className = "Classroom ".$item->name;
                if ($item->major->slug != 'general'){
                    $className = $className." (".strtoupper($item->major->slug).")";
                }

                $dataRecord = [
                    'classroom_id' => $item->id,
                    'name' => $className,
                    'type' => [
                        'raw' => $item->classroom_type,
                        'formatted' => ucwords($item->classroom_type) . " Class"
                    ],
                    'code' => $item->code,
                    'filter_grade' => $item->grade,
                    'filter_type' => $item->classroom_type,
                ];
                array_push($record, $dataRecord);
            }
            if ($item->dormitories()->exists()){
                $className = "Dorm Room ".$item->name;

                $dataRecord = [
                    'classroom_id' => $item->id,
                    'name' => $className,
                    'type' => [
                        'raw' => $item->classroom_type,
                        'formatted' => ucwords($item->classroom_type) . " Class"
                    ],
                    'code' => $item->code,
                    'filter_grade' => $item->grade,
                    'filter_type' => $item->classroom_type,
                ];
                array_push($record, $dataRecord);
            }
        }

        $final = collect($record)->sortBy('filter_grade')->sortByDesc('filter_type')->toArray();

        // Result
        $result = [
            'paginate' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage()
            ],
            'records' => array_values($final)
        ];

        return Response::status('success')->result($result);
    }

    public function createSelectGrade(){
        $user = Auth::user();
        $teacher = $user->teacher;

        // Teacher School or dorm
        if($teacher->schools()->exists()){
            $school = $user->teacher->schools()->first();
        }
        elseif ($teacher->dormitories()->exists()){
            $schoolOrDorm =  $user->teacher->dormitories()->first();
            $school = $schoolOrDorm->schools;
        }

        // Grade
        switch ($school->levels->slug){
            case 'sd':
                $grade = [1,2,3,4,5,6];
                break;
            case 'smp':
                $grade = [7,8,9];
                break;
            case 'sma':
                $grade = [10,11,12];
                break;
            default:
                break;
        }

        $result = [
            'grade' => $grade,
        ];

        return Response::status('success')->result($result);
    }

    public function createSelectClassroom(Request $request){
        $user = Auth::user();
        $teacher = $user->teacher;
        $grade = $request->input('grade');

        // Validate
        $validator = Validator::make($request->all(), [
            'grade' => 'required|numeric',
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

        //School or Dorm
        if ($teacher->schools()->exists()){
            $rel = 'schools';
        }
        elseif ($teacher->dormitories()->exists()){
            $rel = 'dormitories';
        }

        // Query
        $classrooms = Classroom::query()
            ->where('grade', $grade)
            ->where('classroom_type', '=', 'regular')
            ->has($rel)
            ->get();

        $result = [];
        foreach ($classrooms as $classroom){
            if ($classroom->schools()->exists()){
                $classroomName = "Classroom ".$classroom->name;
            }
            elseif($classroom->dormitories()->exists()){
                $classroomName = "Dorm Room ".$classroom->name;
            }

            if ($classroom->major->slug != 'general'){
                $classroomName = $classroomName. " (" . strtoupper($classroom->major->slug) . ")";
            }

            $dataResult = [
                'classroom_id' => $classroom->id,
                'name' => $classroomName
            ];
            array_push($result, $dataResult);
        }

        return Response::status('success')->result($result);
    }

    public function additionalStudent(Request $request){
        // Prerequisites
        $classroomId = $request->input('classroom_id');

        // Validate
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

        // Query
        $classroom = Classroom::query()
            ->with(['students' => function($query){
                $query->where('is_active', true);
            }])
            ->where('id', $classroomId)
            ->first();

        $students = [];
        foreach ($classroom->students as $student) {
            $dataStudents = [
                'student_id' => $student->id,
                'name' => $student->name
            ];
            array_push($students, $dataStudents);
        }

        return Response::status('success')->result($students);
    }

    public function create(Request $request){
        // Prerequisites
        $user = Auth::user();
        $teacher = $user->teacher;
        $students = $request->input('student_id');
        $className = $request->input('title');
        $grade = $request->input('grade');

        $slug = Str::slug($className, '_');
        $upper = Str::upper($slug);

        // Year
        $year = Student::find($students[0])->classrooms()->wherePivot('is_active', true)->first()->pivot->school_year;
        $explodedYear = explode('/', $year);

        // Extra Attributes
        $extraAttributes = ['is_leader' => false, 'is_active' => true, 'school_year' => $year];

        // Classroom Code
        $baseCode = "CE";
        if (Classroom::whereLike('code', $baseCode.$upper."-"."REG"."-".$explodedYear[0])->first() == null){
            $newClassCode = $baseCode.$upper."-".$explodedYear[0]."-1";
        }
        else{
            $latestClass = Classroom::whereLike('code', $baseCode.$upper."-"."REG"."-".$explodedYear[0])->strIntOrderBy('code', 'desc')->first();
            $explodedCode = explode("-", $latestClass->code);
            $newExplodedCode = $explodedCode[2] + 1;

            $newClassCode = $baseCode.$upper."-"."REG"."-".$explodedYear[0]."-".$newExplodedCode;
        }

        // New Record Classroom
        $newClassroom = new Classroom();
        $newClassroom->teacher_id = $teacher->id;
        $newClassroom->name = $className;
        $newClassroom->code = $newClassCode;
        $newClassroom->classroom_type = 'extra';
        $newClassroom->grade = $grade;
        $newClassroom->major_id = 1;
        $newClassroom->capacity = count($students);
        $newClassroom->push();
        $newClassroom->students()->attach($students, $extraAttributes);

        // School Or Dorm
        if ($teacher->schools()->exists()){
            $school = $teacher->schools()->first();
            $newClassroom->schools()->attach($school->id);
        }
        elseif($teacher->dormitories()->exists()){
            $dorm = $teacher->dormitories()->first();
            $newClassroom->dormitories()->attach($dorm->id);
        }

        // Insert Teacher Classroom Relation
        $teacherClassroomAttr = ['school_year' => $year];
        $newClassroom->teacher_classrooms()->attach($teacher->id, $teacherClassroomAttr);
        $newClassroom->push();

        return Response::status('success')->result();
    }

    public function updateDetail(Request $request){
        $classroomId = $request->input('classroom_id');

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

        $classroom = Classroom::with('students')->where('id', $classroomId)->first();

        // Students
        $students = $classroom->students;

        $Students = [];
        foreach ($students as $item){
            $Students[] = [
                'student_id' => $item->id,
                'name' => $item->name
            ];
        }

        $result = [
            'classroom_name' => $classroom->name,
            'students' =>  $Students
        ];

        return Response::status('success')->result($result);
    }

    public function update(Request $request){
        $studentId = $request->input('student_id');
        $classroomId = $request->input('classroom_id');

        // Get Classrooms
        $classroom = Classroom::query()
            ->where('classroom_type', 'extra')
            ->where('id', $classroomId)
            ->first();

        // Validate Classrooms
        if (!$classroom){
            return Response::status('failure')->code(204)->result("Couldn't find any classroom");
        }

        // Syncing the students
        $classroom->students()->sync($studentId);

        return Response::status('success')->code(200)->result(null);
    }

    public function delete(Request $request){
        $classroomid = $request->classroom_id;
        $now = Carbon::now('Asia/Jakarta')->toDateTimeString();

        if (Classroom::find($classroomid)->classroom_type !== 'extra'){
            return Response::status('failure')->result("Selected Class is not extra");
        }

        DB::table('classrooms')
            ->where('id', $classroomid)
            ->update(['deleted_at' => $now]);
        DB::table('classroom_students')
            ->where('classroom_id', $classroomid)
            ->update(['deleted_at' => $now]);
        DB::table('school_classrooms')
            ->where('classroom_id', $classroomid)
            ->update(['deleted_at' => $now]);
        DB::table('dormitory_classrooms')
            ->where('classroom_id', $classroomid)
            ->update(['deleted_at', $now]);
        DB::table('tests')
            ->where('classroom_id', $classroomid)
            ->update(['deleted_at', $now]);
        DB::table('assignments')
            ->where('classroom_id', $classroomid)
            ->update(['deleted_at', $now]);
        DB::table('schedule')
            ->where('classroom_id', $classroomid)
            ->update(['deleted_at', $now]);
        DB::table('lessons')
            ->where('classroom_id', $classroomid)
            ->update(['deleted_at' => $now]);

        return Response::status('success')->code(200)->result(null);
    }

    public function lessonNext(Request $request){
        // Get Teacher id by Auth
        $user = Auth::user();
        $teacherId = $user->teacher->id;
        $classroomId = $request->input('classroom_id');

        // Date & Time now
        $datenow = Carbon::now('Asia/Jakarta')->toDateString();
        $timenow = Carbon::now('Asia/Jakarta')->toTimeString();

        // Querying and Sorting next lesson meet
        $schedule = Schedule::all()
            ->where('teacher_id', $teacherId)
            ->where('classroom_id', $classroomId)
            ->where('date', '>=' ,$datenow)
            ->sortByMulti([
                'date' => 'ASC',
                'start' => 'ASC',
            ]);

        // Validate
        if ($schedule == null){
            return Response::status('failure')->result(["Unexpected error"]);
        }

        if($schedule->first()->classrooms->schools()->exists()){
            $schoolOrDorm = 'Classroom ';
        }
        elseif ($schedule->first()->classrooms->dormitories()->exists()){
            $schoolOrDorm = "Dorm Room ";
        }

        // Today Learning Material
        $now = $schedule->where('date', '=', $datenow)->where('start' , '>=', $timenow)->first();
        if ($now != null){
            $result = [
                'classroom' => $schoolOrDorm.$now->classrooms->name,
                'subject' => $now->subjects->name,
                'subject_code' => $now->subjects->code,
                "grade" => $now->classrooms->grade,
                'main_topic' => $now->main_topic,
                'schedule' => [
                    'time' => Carbon::parse($now->start)->format('g:i A'),
                    'date' => Carbon::parse($now->date)->format('l, d F Y'),
                ],
                'code' => 'meeting code(Still Static)'
            ];
        }
        // Tomorrow Lesson
        else{
            if ($schedule->first() != null) {
                $tomorrow = $schedule->where('date', '>', $datenow)->first();

                if(Carbon::now()->addDay()->toDateString() == $tomorrow->date){
                    $next = "Tomorrow";
                }
                else{
                    $next = Carbon::parse($tomorrow->date)->format('l');
                }

                $result = [
                    'classroom' => $schoolOrDorm.$tomorrow->classrooms->name,
                    'subject' => $tomorrow->subjects->name,
                    'subject_code' => $tomorrow->subjects->code,
                    "grade" => $tomorrow->classrooms->grade,
                    'main_topic' => $tomorrow->main_topic,
                    'schedule' => [
                        'time' => Carbon::parse($tomorrow->start)->format('g:i A'),
                        'date' => $next.", ".Carbon::parse($tomorrow->date)->format('d F Y'),
                    ],
                    'code' => 'meeting code(Still Static)'
                ];
            }

            else{
                return Response::status('failure')->code(204)->result(['Nothing to show here']);
            }
        }
        return Response::status('success')->result($result);
    }

    /**
     * DETAIL
     *
     */
    public function detail(Request $request){
        $classroom = Classroom::find($request->query('id'));

        $result = [
            'id' => $classroom->id,
            'name' => $classroom->name,
        ];

        return Response::status('success')->code(200)->result($result);
    }
}
