<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassroom;
use App\Models\Test;
use App\Models\Assignment;
use App\Models\LearningContract;
use App\Models\StudentAssignment;
use App\Models\StudentTest;
use App\Utils\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TeacherProfileController extends Controller
{
    /**
     * DETAIL
     *
     */
    public function detail(){
        $user = Auth::user();
        $teacher = $user->teacher;

        $result = [
            'image' => $teacher->profile_picture,
            'name' => $teacher->name,
            'role' => [
                'raw' => $user->role->slug,
                'formatted' => $user->role->name,
            ],
            'identification_number' => $teacher->nip,
            'email' => $user->email,
            'phone' => NULL,
            'address' => $teacher->address,
        ];

        return Response::status('success')->code(200)->result($result);
    }

    public function profileClassrooms(){
        $user = Auth::user();
        $teacher = $user->teacher;

        $queryYear = DB::table('teacher_classrooms')
            ->select(DB::raw('min(teacher_id) as teacher_id'), 'school_year')
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('school_year')
            ->groupBy('school_year')
            ->first();
        $year = $queryYear->school_year;

        $classrooms = $teacher->teacher_classrooms()->wherePivot('school_year', $year)->where('classroom_type', 'regular')->get();

        $result = [];
        foreach ($classrooms as $classroom){

            if ($classroom->major->slug == 'science' || $classroom->major->slug == 'sociology'){
                $name = "Classroom " . $classroom->name . " (" . strtoupper($classroom->major->slug) . ")";
            }
            else{
                $name = "Classroom " . $classroom->name;
            }

            $dataResult = [
                'classroom_id' => $classroom->id,
                'name' => $name,
                'grade' => $classroom->grade,
                'school_year' => $year
            ];
            array_push($result, $dataResult);
        }

        return Response::status('success')->result($result);
    }

    public function profileSubjects(Request $request){
        $user = Auth::user();
        $teacher = $user->teacher;
        $classroomId = $request->input('classroom_id');

        if($teacher->schools()->exists()){
            $schoolOrDorm = $teacher->schools()->first();
        }
        if ($teacher->dormitories()->exists()){
            $schoolOrDorm = $teacher->dormitories()->first();
        }

        $query = Schedule::query()
            ->with('subjects')
            ->select(DB::raw('min(id) as id'), DB::raw('min(teacher_id) as teacher_id'), DB::raw('min(classroom_id) as classroom_id'), 'subject_id')
            ->where('classroom_id', $classroomId)
            ->where('teacher_id', $teacher->id)
            ->groupBy('subject_id')
            ->get();

        $result = [];
        foreach ($query as $item){
            $subject = $item->subjects;
            if ($subject->schools()->exists()) {
                $color = $subject->schools()->wherePivot('school_id', $schoolOrDorm->id)->first();
            }

            if ($subject->dormitories()->exists()) {
                $color = $subject->schools()->wherePivot('dormitory_id', $schoolOrDorm->id)->first();
            }

            $dataResult = [
                'subject_id' => $subject->id,
                'subject' => $subject->name,
                'color' => $color->pivot->color,
                'teacher' => $user->name
            ];

            array_push($result, $dataResult);
        }

        return Response::status('success')->result($result);
    }

    public function profileStudents(Request $request){
        $classroomId = $request->input('classroom_id');

        $classroom = Classroom::query()
            ->with(['students' => function($query){
                $query->where('is_active', true);
            }])
            ->where('id', $classroomId)->first();
        if (!$classroom){
            return Response::status('failure')->result("couldn't find classroom");
        }

        $result = [];
        foreach ($classroom->students as $student){
            $dataResult = [
                'student_id' => $student->id,
                'name' => $student->name,
            ];

            array_push($result, $dataResult);
        }

        return Response::status('success')->result($result);
    }

    public function learningProgressStudentDetail(Request $request){
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|exists:classrooms,id',
            'subject_id' => 'required|exists:subjects,id',
            'school_year' => 'required',
            'student_id' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        $user = Auth::user();

        $classroomId = $request->classroom_id;
        $subjectId = $request->subject_id;
        $teacherId = $user->teacher->id;
        $school_year = $request->school_year;
        $studentId = $request->student_id;

        $classroom = Classroom::where('id', $classroomId)->first();
        $subject = Subject::where('id', $subjectId)
            ->with(['teachers'=>function($q){
                $q->select('name');
            }])
            ->first();


       $schedules = Schedule::with(['schedule_attendances' => function($query) use($studentId){
                $query->where('student_id', $studentId);
            }])
            ->select(DB::raw('min(id) as id'), DB::raw('min(subject_id) as subject_id'), DB::raw('min(classroom_id) as classroom_id'), 'date')
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('school_year', $school_year)
            ->groupBy('date')
            ->orderBy('id')->get();
        //get Test

        $present = 0;
        $absent = 0;
        foreach ($schedules as $schedule){
            $perSchedule = $schedule->schedule_attendances->where('status', 'present')->where('student_id', $studentId)->first() != null;

            ($perSchedule)
                ? $present++
                : $absent++;
        }

        $test = Test::where('classroom_id',$classroomId)
        ->where('subject_id',$subjectId)
        ->where('school_year', $school_year)
        ->with(['student_tests'=>function($q) use($studentId){
            $q->select('student_id',DB::raw('min(test_id) as test_id'),DB::raw('max(id) as id'),DB::raw('max(status) as status'));
            $q->where('student_id',$studentId);
            $q->groupBy('student_id');
        }])
        ->get();

        $test_count = 0;
        $test_done = 0;
        foreach($test as $row){
            if(count($row->student_tests)!=0){
                $test_count = $test_count +1;
                $test_done = $row->student_tests->first()->status == 'finish'?($test_done+1):($test_done+0);
            }
        }
        //get task
        $task = Assignment::where('classroom_id',$classroomId)
            ->where('subject_id',$subjectId)
            ->where('school_year', $school_year)
            ->with(['student_assignments'=>function($q) use($studentId){
                $q->select('student_id',DB::raw('min(assignment_id) as assignment_id'),DB::raw('max(id) as id'),DB::raw('max(status) as status'));
                $q->where('student_id',$studentId);
                $q->groupBy('student_id');
            }])
            ->get();

        $task_count= 0;
        $task_done = 0;
        foreach($task as $row){
            if(count($row->student_assignments)!=0){
                $task_count = $task_count +1;
                $test_done = $row->student_assignments->first()->status == 'finish'?($task_done+1):($task_done+0);
            }
        }
        $lc = LearningContract::where('subject_id',$subjectId)
            ->where('teacher_id',$teacherId)
            ->where('school_year', $school_year)
            ->with(['formulas'=>function($q) use($classroomId){
                $q->where('classroom_id',$classroomId);
                $q->with('formula_parameter.formula_parameter_components');
            }])
            ->get();

        $charts = [];
        foreach($lc as $dataLc){
            $semester = 'semester_'.(($dataLc->semester%2)==1?"1":"2");
            $chart = [
                $semester => [
                    'knowledge'=>0,
                    'skill'=>0,
                ]
            ];
            foreach($dataLc->formulas as $formula){
                if($formula->formula_parameter){ // if have formula parameter
                    $cognitive_type = $formula->cognitive_type; //type knowledge/skill
                    $formula_weight = $formula->formula_parameter->weight;//formula weight
                    $formula_parameter = $formula->formula_parameter->parameter; // parameter test or task
                    foreach($formula->formula_parameter->formula_parameter_components as $formula_component){
                        if($formula_parameter == "Assignment"){
                            $score = StudentAssignment::where('id',$formula_component->component)->first()->score; // get score from assignment
                        }else{
                            $score = StudentTest::where('id',$formula_component->component)->first()->score; // get score from test
                        }
                        $chart[$semester][$cognitive_type] = $chart[$semester][$cognitive_type] +(($score * $formula_component->weight/100)*$formula_weight/100);
                    }
                }
            }
            $charts[] = $chart;
        }
        $result = [
            'subject' => $subject->name,
            'grade' => $classroom->grade,
            'teacher'=> $subject->teachers->first()->name,
            'meetings' => [
                'total' => count($schedules),
                'present' => $present,
                'absent' => $absent
            ],
            'test' => [
                'total' => $test_count,
                'submitted' => $test_done,
                'not_submitted' => $test_count-$test_done,
            ],
            'task' => [
                'total' => $task_count,
                'submitted' => $task_done,
                'not_submitted' => $task_count-$task_done,
            ],
            'chart' => $charts
        ];
        return Response::status('success')->result($result);

        // $classroomId = $request->input('classroom_id');
        // $subjectId = $request->input('subject_id');
        // $studentId = $request->input('student_id');

        // /**
        //  *  MAIN QUERY
        //  */
        // $year = Classroom::query()->where('id', $classroomId)->first()->students()->wherePivot('is_active', true)->first()->pivot->school_year;
        // $classroom = Classroom::with(['assignments' => function($query) use($studentId, $subjectId, $year){
        //     $query->where('subject_id', $subjectId);
        //     $query->where('school_year', $year);
        //     $query->with(['student_assignments' => function($q) use ($studentId){
        //         $q->where('student_id', $studentId);
        //     }]);
        // }])
        //     ->with(['tests' => function($query) use($studentId, $subjectId, $year){
        //         $query->where('subject_id', $subjectId);
        //         $query->where('school_year', $year);
        //         $query->with(['student_tests' => function($q) use ($studentId){
        //             $q->where('student_id', $studentId);
        //         }]);
        //     }])
        //     ->where('id', $classroomId)
        //     ->first();

        // /**
        //  * ASSIGNMENTS
        //  */
        // $assignments = $classroom->assignments;
        // $taskFinish = 0;
        // $taskNotFinish = 0;
        // foreach ($assignments as $assignment){
        //     $perTask = $assignment->student_assignments != null;
        //     if ($perTask === true){
        //         $actualTask = $assignment->student_assignments->whereIn('status', ['finish', 'late'])->where('student_id', $studentId) != null;
        //         if ($actualTask === true){
        //             $taskFinish++;
        //         }
        //         else{
        //             $taskNotFinish++;
        //         }
        //     }
        //     else{
        //         $taskNotFinish++;
        //     }
        // }

        // /**
        //  * TESTS
        //  */
        // $tests = $classroom->tests;
        // $testFinish = 0;
        // $testNotFinish = 0;
        // foreach ($tests as $test){
        //     $pertTest = $test->student_assignments != null;
        //     if ($pertTest === true){
        //         $actualTest = $test->student_assignments->where('status', 'finish')->where('student_id', $studentId) != null;

        //         if ($actualTest === true){
        //             $testFinish++;
        //         }
        //         else{
        //             $testNotFinish++;
        //         }
        //     }
        //     else{
        //         $testNotFinish++;
        //     }
        // }

        // /**
        //  * SCHEDULES
        //  */
        // $schedules = Schedule::query()
        //     ->with(['schedule_attendances' => function($query) use($studentId){
        //         $query->where('student_id', $studentId);
        //     }])
        //     ->select(DB::raw('min(id) as id'), DB::raw('min(subject_id) as subject_id'), DB::raw('min(classroom_id) as classroom_id'), 'date')
        //     ->where('classroom_id', $classroomId)
        //     ->where('subject_id', $subjectId)
        //     ->groupBy('date')
        //     ->orderBy('id')->get();

        // // Present and Absent Student during Meet
        // $present = 0;
        // $absent = 0;
        // foreach ($schedules as $schedule){
        //     $perSchedule = $schedule->schedule_attendances->where('status', 'present')->where('student_id', $studentId)->first() != null;
        //     if($perSchedule === true){
        //         $present ++;
        //     }
        //     elseif ($perSchedule === false){
        //         $absent ++;
        //     }
        // }

        // $result = [
        //     'meetings' => [
        //         'total' => count($schedules),
        //         'present' => $present,
        //         'absent' => $absent
        //     ],
        //     'tasks' => [
        //         'total' => count($classroom->assignments),
        //         'submitted' => $taskFinish,
        //         'not_submitted' => $taskNotFinish
        //     ],
        //     'tests' => [
        //         'total' => count($classroom->tests),
        //         'submitted' => $testFinish,
        //         'not_submitted' => $testNotFinish
        //     ],
        //     'chart' => [
        //         'semester_1' => [
        //             'knowledge' => "still static",
        //             'skill' => "still static"
        //         ],
        //         'semester_2' => [
        //             'knowledge' => "still static",
        //             'skill' => "still static"
        //         ]
        //     ]
        // ];

        // return Response::status('success')->result($result);
    }

    public function attendanceHistoryStudentDetail(Request $request){
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');
        $studentId = $request->input('student_id');
        $year = $request->input('school_year');

        // Validate
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required',
            'subject_id' => 'required',
            'student_id' => 'required',
            'school_year' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

//        $year = Student::find($studentId)->classroom()->wherePivot('is_active', true)->where('classroom_type', 'regular')->first()->pivot->school_year;
        $schedules = Schedule::query()
            ->with(['schedule_attendances' => function($query) use($studentId){
                $query->where('student_id', $studentId);
            }])
            ->select(DB::raw('min(id) as id'), DB::raw('min(subject_id) as subject_id'), DB::raw('min(classroom_id) as classroom_id'), 'date')
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('school_year', $year)
            ->groupBy('date')
            ->orderBy('id')->get();

        // Present and Absent Student Meet
        $present = 0;
        $sick = 0;
        $permission = 0;
        $alpha = 0;
        $notCheckedYet = 0;
        foreach ($schedules as $schedule){
            $perSchedule = $schedule->schedule_attendances->whereIn('status', ['present', 'sick', 'absent', 'permit'])->where('student_id', $studentId)->first();
            if ($perSchedule != null) {
                switch ($perSchedule->status) {
                    case "present":
                        $present++;
                        break;
                    case "sick":
                        $sick++;
                        break;
                    case "permit":
                        $permission++;
                        break;
                    case "absent":
                        $alpha++;
                        break;
                    default:
                        break;
                }
            }
            else{
                $notCheckedYet ++;
            }
        }

        $result = [
                "total" => count($schedules),
                "present" => $present,
                "absent" => $sick+$permission+$alpha,
                "sick" => $sick,
                "permit" => $permission,
                "alpha" => $alpha,
                "haven't checked yet" => $notCheckedYet
        ];

        return Response::status('success')->result($result);
    }
}
