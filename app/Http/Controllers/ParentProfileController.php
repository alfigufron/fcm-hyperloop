<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Test;
use App\Models\Assignment;
use App\Models\LearningContract;
use App\Models\StudentAssignment;
use App\Models\StudentTest;
use App\Utils\Paginate;
use App\Utils\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ParentProfileController extends Controller
{
    /**
     * DETAIL
     * 
     */
    public function detail(Request $request){
        $user       = Auth::user();
        $role       = $user->role;
        $family     = $user->family;
        $studentId  = $request->query('student_id');

        $student = Student::whereHas('student_families', function($q) use($family){
            $q->where('family_id', $family->id);
        })->when($studentId, function($q) use($studentId){
            $q->where('id', $studentId);
        })->first();

        if(!$student){
            return Response::status('failure')->code(404)->result(["Data not found!"]);
        }

        $result = [
            'image' => public_path().$family->profile_picture,
            'name' => $family->name,
            'role' => [
                'raw' => $role->slug,
                'formatted' => $role->name,
            ],
            'student' => [
                'id' => $student->id,
                'name' => $student->name
            ],
            'email' => $user->email,
            'phone' => $family->phone,
            'address' => $family->address
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * ATTENDANCE
     * 
     */
    public function attendance(Request $request){
        $studentId  = $request->query('student_id');

        $student = Student::where('id', $studentId)->with(['classrooms' => function($q) use($studentId){
            $q->whereHas('classroom_students', function($q) use($studentId){
                $q->where('student_id', $studentId);
                $q->where('is_active', TRUE);
            });
        }])->with('dormitory_students')->first();

        $classrooms = Classroom::whereIn('classroom_type', ['regular'])->whereHas('classroom_students', function($q) use($studentId){
            $q->where('student_id', $studentId);
            $q->where('is_active', TRUE);
        })->with(['schedules' => function($q) use($student, $studentId){
            $q->where('school_year', $student->classrooms[0]->classroom_students[0]->school_year);
            $q->with(['subject' => function($q) use($student){
                $q->with(['school_subjects' => function($q) use($student){
                    $q->where('school_id', $student->school_id);
                }]);
                $q->with(['dormitory_subjects' => function($q) use($student){
                    $q->where('dormitory_id', $student->dormitory_students->last()->dormitory_id);
                }]);
            }]);
            $q->with('teacher');
        }])->get();

        $schedules = [];
        foreach($classrooms as $classroom){
            foreach($classroom->schedules as $schedule){
                array_push($schedules, $schedule);
            }
        }

        $collection = collect($schedules);
        $collection = $collection->groupBy('subject_id');

        $dataPagination = [];
        foreach($collection as $c){
            $data = $c[0];

            $dataPush = [
                'classroom_id' => $data->classroom_id,
                'subject_id' => $data->subject_id,
                'subject' => $data->subject->name,
                'teacher' => $data->teacher->name,
                'grade' => "Grade ".$student->classrooms[0]->grade,
                'color' => $data->subject->school_subjects[0]->color ?? $data->subject->dormitory_subjects[0]->color,
            ];

            array_push($dataPagination, $dataPush);
        }

        // Pagination
        $paginate = Paginate::paginator($dataPagination, 10);
        $pagination = [
            'total_page' => $paginate->lastPage(),
            'total_records' => $paginate->total(),
            'current_page' => $paginate->currentPage()
        ];

        $result = [
            'pagination' => $pagination,
            'records' => $paginate->items()
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * ATTENDANCE DETAIL
     * 
     */
    public function attendanceDetail(Request $request){
        $studentId      = $request->query('student_id');
        $subjectId      = $request->query('subject_id');
        $classroomId    = $request->query('classroom_id');

        $classroom = Classroom::where('id', $classroomId)->whereHas('classroom_students', function($q) use($studentId){
            $q->where('student_id', $studentId);
            $q->where('is_active', TRUE);
        })->with(['schedules' => function($q) use($subjectId, $studentId){
            $q->where('subject_id', $subjectId);
            $q->has('schedule_attendances');
            $q->with(['schedule_attendances' => function($q) use($studentId){
                $q->where('student_id', $studentId);
            }]);
        }])->first();

        $subject    = $classroom->schedules[0]->subject->name;
        $teacher    = $classroom->schedules[0]->teacher->name;
        $grade      = "Grade ".$classroom->grade;

        $present = 0;
        $sick = 0;
        $permit = 0;
        $absent = 0;
        $totalAbsent = 0;
        $total = 0;
        foreach($classroom->schedules as $schedule){
            switch($schedule->schedule_attendances[0]->status){
                case 'present':
                    $present++;
                    break;
                case 'sick':
                    $sick++;
                    break;
                case 'permit':
                    $permit++;
                    break;
                case 'absent':
                    $absent++;
                    break;
            }
            $total++;
        }

        $result = [
            'subject' => $subject,
            'teacher' => $teacher,
            'grade' => $grade,
            'attendance' => [
                'present' => $present,
                'absent' => [
                    'sick' => $sick,
                    'permit' => $permit,
                    'absent' => $absent
                ],
                'total' => $total
            ]
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * LEARNING PROGRESS
     * 
     */
    public function learningProgress(Request $request){
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|exists:classrooms,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required',
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
        $teacherId = $request->teacher_id;
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
        // $validator = Validator::make($request->all(), [
        //     'classroom_id' => 'required|exists:classrooms,id',
        //     'subject_id' => 'required|exists:subjects,id',
        //     'student_id' => 'required|exists:students,id',
        // ]);

        // if ($validator->fails()) {
        //     $errors = $validator->errors();
        //     $error = [];
        //     foreach ($errors->all() as $message)
        //         array_push($error, $message);

        //     return Response::status('failure')->code(422)->result($error);
        // }

        // $user = Auth::user()
        //     ->family
        //     ->student_families
        //     ->where('student_id', $request->student_id)
        //     ->first()
        //     ->student;

        // $classroomId = $request->classroom_id;
        // $subjectId = $request->subject_id;
        // $studentId = $user->id;

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

        // foreach ($assignments as $assignment) {
        //     $perTask = $assignment->student_assignments != null;
        //     if ($perTask === true){
        //         $actualTask = $assignment->student_assignments->whereIn('status', ['finish', 'late'])->where('student_id', $studentId) != null;
                
        //         ($actualTask === true)
        //             ? $taskFinish++
        //             : $taskNotFinish++;
        //     } else {
        //         $taskNotFinish++;
        //     }
        // }

        // /**
        //  * TESTS
        //  */
        // $tests = $classroom->tests;
        // $testFinish = 0;
        // $testNotFinish = 0;
        // foreach ($tests as $test) {
        //     $pertTest = $test->student_assignments != null;
        //     if ($pertTest) {
        //         $actualTest = $test->student_assignments->where('status', 'finish')->where('student_id', $studentId) != null;

        //         ($actualTest === true)
        //             ? $testFinish++
        //             : $testNotFinish++;
        //     } else {
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
            
        //     ($perSchedule)
        //         ? $present++
        //         : $absent++;
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

    /**
     * ATTENDANCE HISTORY
     * 
     */
    public function attendanceHistory(Request $request){
        $studentId = $request->input('student_id');
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');
        $teacherId = $request->input('teacher_id');

        $student = Student::find($studentId);

        // Year
        $year = $student->classrooms()->wherePivot('is_active', true)->first()->pivot->school_year;

        $schedule = Schedule::query()
            ->with(['schedule_attendances' => function($query) use($student){
                $query->where('student_id', $student->id);
            }])
            ->select(DB::raw('min(id) as id'), DB::raw('min(teacher_id) as teacher_id'),
                DB::raw('min(subject_id) as subject_id'), DB::raw('min(classroom_id) as classroom_id'), 'date')
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->where('school_year', $year)
            ->orderBy('id')
            ->groupBy('date')
            ->get();

        $total = 0;
        $present = 0;
        $absent = 0;
        $sick = 0;
        $permit = 0;
        foreach ($schedule as $item){
            $total += count($item->schedule_attendances);
            $present += count($item->schedule_attendances->where('status', 'present'));
            $absent += count($item->schedule_attendances->where('status', 'absent'));
            $sick += count($item->schedule_attendances->where('status', 'sick'));
            $permit += count($item->schedule_attendances->where('status', 'permit'));
        }

        $data = [
            'total' => $total,
            'present' => $present,
            'absent' => $absent+$sick+$permit,
            'alpha' => $absent,
            'sick' => $sick,
            'permit' => $permit
        ];

        return Response::status('success')->result($data);
    }

    public function dashboard(Request $request){
        // Prerequisites
        $user = Auth::user();
        $student = $user->student;

        // Query Card
        $now = Carbon::now()->toDateString();
        $school = $student->schools()->first();
        $agenda = $school->agendas()->get()->where('date', $now);
        $announcement = $school->announcements()->get();

        // Card
        $offence = count($student->offences);
        $achievement = count($student->achievement);
        $announcement = count($agenda);
        $duePayment = "Still Static";

        // Attendance
        $present = $student->scheduleattendances()->where('status', 'present')->get();
        $absent = $student->scheduleattendances()->where('status', 'absent')->get();
        $sick = $student->scheduleattendances()->where('status', 'sick')->get();
        $permit = $student->scheduleattendances()->where('status', 'permit')->get();

        $result = [
            'attendances' => [
                'present' => count($present),
                'absent' => count($absent),
                'sick' => count($sick),
                'permit' => count($permit),
            ],
            'offence' => $offence,
            'achievement' => $achievement,
            'announcement' => $announcement,
            'payment_due' => $duePayment
        ];

        return Response::status('success')->result($result);
    }
}
