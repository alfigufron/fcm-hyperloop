<?php


namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\StudentAssignment;
use App\Models\Subject;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\StudentTest;
use Illuminate\Support\Facades\DB;
use App\Utils\Ordinal;

use App\Utils\Paginate;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TeacherForumScoreController extends Controller
{

    public function header(Request $request){
        $subjectid = $request->subject_id;
        $taskid = $request->task_id;
        $testid = $request->test_id;

        $subject = Subject::find($subjectid);
        $task = Assignment::find($taskid);
        $test = Test::find($testid);

        $second = null;
        if ($request->query('test_id') != null){
            $second = $test->learning_contract_information->main_topic;
        }
        elseif ($request->query('task_id') != null){
            $second = $task->learning_contract_information->main_topic;
        }

        $result = [
            'first' => $subject->name,
            'second' => $second,
        ];

        return Response::status('success')->result($result);
    }

    // Score task and test
    public function scorelist(Request $request){
        //Prerequisites
        $type = $request->query('type');
        $classroomId = $request->classroom_id;
        $subjectId = $request->subject_id;
        $search = $request->query('q');

        $classroom = Classroom::find($classroomId);
        foreach ($classroom->students as $student){
            $true = $student->pivot->where('is_active', true)->first();
            $year = $true->school_year;
        }

        // Data
        switch ($type){
            case 'task':
                $tasks = Assignment::query()
                    ->where('classroom_id', $classroomId)
                    ->where('subject_id', $subjectId)
                    ->where('school_year', $year)
                    ->orderBy('created_at');

                    if ($search != null) {
                        $tasks = $tasks->where('title', 'like', '%' . $search . '%')->get();
                    }
                    else{
                        $tasks = $tasks->get();
                    }

                    $tests = [];
                break;
            case 'test':
                $tests = Test::query()
                    ->where('classroom_id', $classroomId)
                    ->where('subject_id', $subjectId)
                    ->where('school_year', $year)
                    ->orderBy('created_at');

                    if ($search != null) {
                        $tests = $tests->where('title', 'like', '%' . $search . '%')->get();
                    }
                    else{
                        $tests = $tests->get();
                    }

                    $tasks = [];
                break;
            default:
                $tasks = Assignment::query()
                    ->where('classroom_id', $classroomId)
                    ->where('subject_id', $subjectId)
                    ->where('school_year', $year)
                    ->orderBy('created_at');

                $tests = Test::query()
                    ->where('classroom_id', $classroomId)
                    ->where('subject_id', $subjectId)
                    ->where('school_year', $year)
                    ->orderBy('created_at');

                if ($search != null) {
                    $tasks = $tasks->where('title', 'like', '%' . $search . '%')->get();

                    $tests = $tests->where('title', 'like', '%' . $search . '%')->get();
                }
                else{
                    $tasks = $tasks->get();

                    $tests = $tests->get();
                }
                break;
        }

        // Foreach Task
        $id = 1;
        $records = [];
        foreach ($tasks as $task){
            $resTask = [
                'id' => $id++,
                'type' => 'task',
                'task_id' => $task->id,
                'name' => $task->title,
                'subject' => $task->subject->name,
                'main_topic' => $task->learning_contract_information->main_topic,
                'filter_time' => Carbon::parse($task->created_at)->toDateTimeString(),
                'filter_type' => 'b'
            ];
            array_push($records, $resTask);
        }

        foreach ($tests as $test){
            $resTest = [
                'id' => $id++,
                'type' => 'test',
                'test_id' => $test->id,
                'name' => $test->title,
                'subject' => $test->subject->name,
                'main_topic' => $test->learning_contract_information->main_topic,
                'filter_time' => Carbon::parse($test->created_at)->toDateTimeString(),
                'filter_type' => 'a'
            ];
            array_push($records, $resTest);
        }

        // Validating request type
        $list = collect($records)->sortByMulti(['filter_time' => 'DESC', 'filter_type' => 'ASC'])->toArray();

        // Pagination
        $data = Paginate::paginator($list);

        // Result
        $result = [
            'pagination' => [
                'total_page' => $data->lastPage(),
                'total_records' => $data->total(),
                'current_page' => $data->currentPage()
            ],
            'records' => array_values($data->items())
        ];

        return Response::status('success')->code(200)->result($result);
    }

    // Score task info
    public function taskinfo(Request $request){
        $taskid = $request->task_id;
        $task = Assignment::find($taskid);

        // Validate
        if ($task == null){
            return Response::status('failure')->result(['Unexpected Task']);
        }

        $result = [
            'name' => $task->title,
            'material' => $task->learning_contract_information->main_topic,
        ];

        return Response::status('success')->result($result);
    }

    // Score task detail
    public function taskdetail(Request $request){
        $taskId = $request->input('task_id');

        // Validation
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|numeric'
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
        $task = Assignment::with('classroom')
            ->with('student_assignments')
            ->where('id', $taskId)
            ->first();

        // Validate Task
        if ($task == null){
            return Response::status('failure')->code(204)->result(["Couldn't find task"]);
        }

        // Query Classroom
        $classroom = $task->classroom;
        if ($classroom->schools()->exists()){
            $rel = $classroom->with(['schools' => function($query){
                $query->with('school_minimum_competency');
            }])
                ->where('id', $classroom->id)
                ->first();

            $minimum_competency = $rel->schools->first()->school_minimum_competency->value;
        }
        elseif ($classroom->dormitories()->exists()){
            $rel = $classroom->with(['dormitories' => function($query){
                $query->with('dormitory_minimum_competency');
            }])
                ->where('id', $classroom->id)
                ->first();

            $minimum_competency = $rel->dormitories->first()->dormitory_minimum_competency->value;
        }

        // Query Student Assignment
        $studentAssignment = $task->student_assignments->where('score', '<', $minimum_competency)->get();

        // Validate Student Assignment
        if ($studentAssignment->first() == null){
            return Response::status('failure')->code(204)->result(["Couldn't find any students"]);
        }

        // Paginate
        $page = Paginate::paginator($studentAssignment);
        $lastPage = $page->lastPage();
        $total = $page->total();
        $curPage = $page->currentPage();
        $items = $page->items();

        // Foreach
        foreach ($items as $item){
            if($item->status == 'finish'){
                $status = "Collected on time";
            }
            if($item->status == 'late'){
                $status = "Collected late";
            }
            elseif($item->status == 'unbegin'){
                $status = "Not yet collected";
            }

            $records[] = [
                'name' => $item->students->name,
                'profile_picture' => $item->students->student_detail->profile_picture,
                'score' => [
                    'get' => $item->score,
                    'from' => $task->total_point
                ],
                'status' => $status
            ];
        }

        $result = [
            'paginate' => [
                'total_page' => $lastPage,
                'total_records' => $total,
                'current_page' => $curPage,
            ],
            'records' => $records
        ];

        return Response::status('success')->result($result);
    }

    // Score Task Remedial // Need Improvement post(database for remedial)
    public function taskremedial(Request $request){
        // Query
        $taskid = $request->task_id;
        $classroomid = $request->classroom_id;
        $classroom = Classroom::find($classroomid);

        $task = Assignment::with(['classroom' => function($query){
               switch ($query->has('schools')->orhas('dormitories')){
                   case 'schools':
                       $query->with(['schools' => function($q){
                           $q->with('school_minimum_competency');
                       }]);
                       break;
                   case 'dormitories':
                       $query->with(['dormitories' => function($q){
                           $q->with('dormitory_minimum_competency');
                       }]);
                       break;
               }
            }])
            ->where('id', $taskid)
            ->first();


        $studenttask = StudentAssignment::where('assignment_id', $taskid)
            ->where('score', '<=', 70);

        // Paginate
        $page = $studenttask->paginate(10);
        $lastpage = $page->lastPage();
        $total = $page->total();
        $perpage = $page->currentPage();
        $items = $page->items();

        // Foreach students
        foreach ($items as $item){
            $records[] = [
                'name' => $item->students->name,
                'profile_picture' => $item->students->detail->profile_picture,
                'score' => [
                    'get' => $item->score,
                    'from' => $task->total_point
                ],
                'status' => $item->status
            ];
        }

        $result = [
            'pagination' => [
                'total_page' => $lastpage,
                'total_record' => $total,
                'current_page' => $perpage
            ],
            'records' => $records
        ];

        return Response::status('success')->result($result);
    }

    public function submitTaskRemedial(Request $request){
        $studentIds = $request->student_id;
        $taskId = $request->task_id;

        $oldTask = Assignment::find($taskId);

        $newTask = new Assignment();
        $newTask->classroom_id = $oldTask->classroom_id;
        $newTask->subject_id = $oldTask->subject_id;
        $newTask->learning_contract_information_id = $oldTask->learning_contract_information_id;
        $newTask->title = $oldTask->title;
        $newTask->description = $oldTask->description;
        $newTask->total_point = $oldTask->total_point;
        $newTask->deadline = $request->deadline;
        $newTask->school_year = $oldTask->school_year;
        $newTask->save();

        // Insert Student
        $studentAssignment = [];
        foreach ($studentIds as $studentId){
            $dataStudentAssignment = [
                'student_id' => $studentId,
                'assignment_id' => $newTask->id,
                'answer' => null,
                'score ' => null,
                'status' => 'unbegin',
                'finish_at' => null
            ];
            array_push($studentAssignment, $dataStudentAssignment);
        }
        $newTask->student_assignments()->createMany($studentAssignment);

        return Response::status('success')->code(201)->result(null);
    }

    // Score Test info
    public function testinfo(Request $request){
        $testid = $request->test_id;
        $test = Test::find($testid);

        $result = [
            'name' => $test->title,
            'material' => $test->learning_contract_information->main_topic,
        ];

        return Response::status('success')->result($result);
    }

    // Score Test detail
    public function testdetail(Request $request){
        // Prerequisites
        $testid = $request->test_id;
        $belowScore = $request->input('below_score');

        // Get minimum competency
        $raw = Test::query()->where('id', $testid)->first();
        $classroom = $raw->classroom;
        if ($classroom->schools()->exists()){
            $school = $classroom->schools()->first();

            if ($school->name == 'SD IIBS' || $school->name == 'SMP IIBS'){
                $minimumCompetency = Subject::where('id', $raw->subject_id)->first()->minimum_competency;
            }
            else{
                $minimumCompetency = $school->school_minimum_competency->value;
            }
        }
        elseif ($classroom->dormitories()->exists()){
            $school = $classroom->dormitories()->first()->schools->name;

            if ($school->name == 'SD IIBS' || $school->name == 'SMP IIBS'){
                $minimumCompetency = Subject::where('id', $raw->subject_id)->first()->minimum_competency;
            }
            else{
                $minimumCompetency = $school->school_minimum_competency->value;
            }
        }

        // Test
        $test = Test::query()
            ->where('id', $testid)
            ->with(['test_question_items' => function($q){
                $q->with(['question_item' => function($qu){
                    $qu->with('question_item_answers');
                }]);
            }])
            ->with(['student_tests' => function($q) use ($minimumCompetency, $belowScore) {
                $q->select(DB::raw('max(id) as id'), DB::raw('min(test_id) as test_id'),'student_id',
                            DB::raw('bool_or(is_allowed)'), DB::raw('min(status) as status'),
                            DB::raw('max(score) as score'), DB::raw('max(student_start_at) as student_start_at'),
                            DB::raw('max(student_end_at) as student_end_at'), DB::raw('max(created_at) as created_at'));
                $q->groupBy('student_id');
                if($belowScore == 'true'){
                    $q->where('score', '<', $minimumCompetency);
                }
//                $q->orderByDesc('created_at');
                $q->where('is_allowed', true);
                $q->with('student_test_answers');
            }])
            ->first();

        $record = [];
        $id = 1;
        foreach ($test->student_tests as $st){

            $correct = 0;
            $wrong = 0;
            foreach ($test->test_question_items as $tqi){
                $testanswer = $st->student_test_answers->where('test_question_item_id', $tqi->id);
                if($testanswer->first() == null){
                    $wrong++;
                }
                elseif ($testanswer->first() != null){
                    if ($testanswer->first()->score > 0){
                        $correct++;
                    }
                    elseif($testanswer->first()->score == 0){
                        $wrong++;
                    }
                }
            }

            if ($st->score == null && in_array($st->status, ['finish', 'late'])){
                $statusStudentTest = "This Student haven't checked yet";
            }
            elseif ($st->score != null && in_array($st->status, ['finish', 'late'])){
                $statusStudentTest = "This Student has been checked";
            }
            elseif($st->score  == null && in_array($st->status, ['ongoing', 'pending'])){
                $statusStudentTest = "This Student haven't submitted the test";
            }

            // Scores Student History
            $i = 1;
            $scores = [];
            $studentTests = StudentTest::query()
                ->where('student_id', $st->student_id)
                ->where('test_id', $test->id)
                ->where('score', '!=', null)
                ->get();
            foreach ($studentTests as $studentTest){
                $scores[] = [
                    'score' => $studentTest->score,
                    'attempt' => Ordinal::number($i++) . ' attempt',
                    'finished_at' => $studentTest->student_end_at
                ];
            }


            $dataStudentTest = [
                'id' => $id++,
                'name' => $st->student->name,
                'status' => [
                    'raw' => $st->status,
                    'formatted' =>$statusStudentTest,
                ],
                'answers' => [
                    'correct' => $correct,
                    'wrong' => $wrong
                ],
                'scores' => $scores,
                'highest_score' => $st->score,
                'test_score' => $test->total_point,
                'is_remedial' => $st->is_remedial,

            ];
            array_push($record, $dataStudentTest);
        }

        // Paginate
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

    // Score Test Remedial // Need Improvement post(database for remedial)
    public function testremedial(Request $request){
        $testId = $request->input('test_id');
        // Validate Query Param
        $validator = Validator::make($request->all(), [
            'test_id' => 'required|numeric'
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

        // Query Test
        $test = Test::with('classroom')
            ->with(['test_question_items' => function($q){
                $q->with(['question_item' => function($qu){
                    $qu->with('question_item_answers');
                }]);
            }])
            ->with(['student_tests' => function($q){
                $q->where('is_allowed', true);
                $q->with('student_test_answers');
            }])
            ->where('id', $testId)
            ->first();

        // Validate Test
        if ($test == null){
            return Response::status('failure')->code(204)->result(["Couldn't find test"]);
        }

        // Query Classroom
        $classroom = $test->classroom;
        if ($classroom->schools()->exists()){
            $rel = $classroom->with(['schools' => function($query){
                $query->with('school_minimum_competency');
            }])
                ->where('id', $classroom->id)
                ->first();

            $minimum_competency = $rel->schools->first()->school_minimum_competency->value;
        }
        elseif ($classroom->dormitories()->exists()){
            $rel = $classroom->with(['dormitories' => function($query){
                $query->with('dormitory_minimum_competency');
            }])
                ->where('id', $classroom->id)
                ->first();

            $minimum_competency = $rel->dormitories->first()->dormitory_minimum_competency->value;
        }

        $studentTest = $test->student_tests->where('score', '<', $minimum_competency);

        $record = [];
        foreach ($studentTest as $st){
            $correct = 0;
            $wrong = 0;
            foreach ($test->test_question_items as $tqi){
                $testanswer = $st->student_test_answers->where('test_question_item_id', $tqi->id);
                if($testanswer->first() == null){
                    $wrong++;
                }
                elseif ($testanswer->first() != null){
                    if ($testanswer->first()->score > 0){
                        $correct++;
                    }
                    elseif($testanswer->first()->score == 0){
                        $wrong++;
                    }
                }
            }

            if ($st->score == null && in_array($st->status, ['finish', 'late'])){
                $statusStudentTest = "This Student haven't checked yet";
            }
            elseif ($st->score != null && in_array($st->status, ['finish', 'late'])){
                $statusStudentTest = "This Student has been checked";
            }

            if ($st->status == 'late'){
                $collectStatus = "Collected late";
            }
            elseif ($st->status == 'finish'){
                $collectStatus = "Collected on time";
            }
            elseif ($st->status == 'pending'){
                $collectStatus = "not yet collected";
            }

            $dataStudentTest = [
                'name' => $st->student->name,
                'collect_status' => $collectStatus,
                'status' => $statusStudentTest,
                'score' => [
                    'get' => $st->score,
                    'from' => $test->score
                ],
                'answers' => [
                    'correct' => $correct,
                    'wrong' => $wrong
                ]
            ];
            array_push($record, $dataStudentTest);
        }

        // Paginate
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

    public function testRemedials(Request $request){
        $isremedial = $request->is_remedial;
        $testid = $request->test_id;
        $validator = Validator::make($request->all(), [
            'test_id' => 'required|numeric',
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

        // Get minimum competency
        $raw = Test::query()->where('id', $testid)->first();
        $classroom = $raw->classroom;
        if ($classroom->schools()->exists()){
            $school = $classroom->schools()->first();

            if ($school->name == 'SD IIBS' || $school->name == 'SMP IIBS'){
                $minimumCompetency = Subject::where('id', $raw->subject_id)->first()->minimum_competency;
            }
            else{
                $minimumCompetency = $school->school_minimum_competency->value;
            }
        }
        elseif ($classroom->dormitories()->exists()){
            $school = $classroom->dormitories()->first()->schools->name;

            if ($school->name == 'SD IIBS' || $school->name == 'SMP IIBS'){
                $minimumCompetency = Subject::where('id', $raw->subject_id)->first()->minimum_competency;
            }
            else{
                $minimumCompetency = $school->school_minimum_competency->value;
            }
        }

        $test = Test::where('id',$testid)
            ->with('learning_contract_information')
            ->first();

        if($isremedial ==true){
            $studentTest = StudentTest:://select(DB::raw('min(id) as id'),DB::raw('max(score) as score'),DB::raw('min(student_end_at) as student_end_at'),DB::raw('bool_or(is_remedial) as is_remedial'),DB::raw('max(created_at) as created_at'),'student_id')
            where('test_id',$testid)
            ->with('student.schools.school_minimum_competency')
            ->with('student.student_detail')
            ->where('score', '<', $minimumCompetency)
            // ->groupBy('student_id')
            ->orderBy("created_at")
            ->get();
        }else{
            $studentTest = StudentTest:://select(DB::raw('min(id) as id'),DB::raw('max(score) as score'),DB::raw('min(student_end_at) as student_end_at'),DB::raw('bool_or(is_remedial) as is_remedial'),DB::raw('max(created_at) as created_at'),'student_id')
            where('test_id',$testid)
            ->with('student.student_detail')
            ->orderBy("created_at")
            ->get();

        }

        $students=[];
        $studentsAttempts=[];
        $attempt=[];
        foreach($studentTest as $row){
            if(empty($students[$row->student_id])){
                $score = $row->score;
            }else if($students[$row->student_id]['high_score']==null){
                $score = $row->score;
            }else if($students[$row->student_id]['high_score'] > $row->score){
                $score = $students[$row->student_id]['high_score'];
            }else{
                $score = $row->score;
            }

            $students[$row->student_id]=[
                'id' =>$row->student_id,
                'name' =>$row->student->name,
                'profile_picture' =>$row->student->student_detail->profile_picture,
                'high_score'=> $score,
                'last_score' => $row->score,
            ];
            $attempt[$row->student_id][] =$row->id;

            $studentsAttempts[$row->student_id][]= [
                "attempt" =>  Ordinal::number(count($attempt[$row->student_id])) . " attempt",
                "score" => $row->score,
                "student_end_at" => $row->student_end_at,
                "test_start_at" => $row->start_at,
            ];
        }
        $resultStudent=[];
        foreach($students as $row){
            $lastAttempt=end($studentsAttempts[$row['id']]);
            $remedial = $row['high_score'] < $minimumCompetency?true:false ;
            $row['have_replacement'] = $remedial===false?"":$lastAttempt['score']?false:true ;
            $row['replacement_start_at'] = $row['have_replacement']===false?null:$lastAttempt['test_start_at'];
            $row['attempts'] =$studentsAttempts[$row['id']];
            $resultStudent[]=$row;
        }

        $result= [
            'title'=> $test->title,
            'learning_contract_main_topic' => $test->learning_contract_information->main_topic,
            'learning_contract_sub_topic' => $test->learning_contract_information->sub_topic,
            'studnet_competency' => $minimumCompetency,
            'total_point' => $test->total_point,
            'start_at' => Carbon::parse($test->start_at)->format('l, d F Y G:i'),//date("H:i d F Y",strtotime($test->start_at)),
            'end_at' => Carbon::parse($test->end_at)->format('l, d F Y G:i'),//date("H:i d F Y",strtotime($test->end_at)),
            'students' => $resultStudent
        ];
        return Response::status('success')->code(200)->result($result);

    }

    public function submitTestRemedial(Request $request){
        $studentIds = $request->student_id;
        $validator = Validator::make($request->all(), [
            'test_id' => 'required|numeric',
            'student_id' => 'required',
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

        $testId = $request->test_id;

        $data=[];
        foreach($studentIds as $row){
            $data[]= [
                'student_id' => $row->student_id,
                'test_id' => $row->test_id,
                'start_at' => $request->input('start_at')['date']." ".$request->input('start_at')['time'],
                'end_at' => $request->input('end_at')['date']." ".$request->input('end_at')['time'],
                'status' => 'pending',
                'is_allowed' => true,
                'score' => null,
                'is_remedial' => true,
            ];
        }

        $studentTest = StudentTest::insert($data);

        return Response::status('success')->code(200)->result($studentTest);

    }

    public function taskRemedials(Request $request){
        $isremedial = $request->is_remedial;
        $taskid = $request->task_id;
        $task = Assignment::where('id',$taskid)->with('learning_contract_information','subject')->first();

        // Get minimum competency
        $raw = Assignment::query()->where('id', $taskid)->first();
        $classroom = $raw->classroom;
        if ($classroom->schools()->exists()){
            $school = $classroom->schools()->first();

            if ($school->name == 'SD IIBS' || $school->name == 'SMP IIBS'){
                $minimumCompetency = Subject::where('id', $raw->subject_id)->first()->minimum_competency;
            }
            else{
                $minimumCompetency = $school->school_minimum_competency->value;
            }
        }
        elseif ($classroom->dormitories()->exists()){
            $school = $classroom->dormitories()->first()->schools->name;

            if ($school->name == 'SD IIBS' || $school->name == 'SMP IIBS'){
                $minimumCompetency = Subject::where('id', $raw->subject_id)->first()->minimum_competency;
            }
            else{
                $minimumCompetency = $school->school_minimum_competency->value;
            }
        }

        if($isremedial ==true){
            $studentTask = StudentAssignment::select(DB::raw('min(id) as id'),DB::raw('min(score) as score'),DB::raw('min(finish_at) as finish_at'),DB::raw('bool_or(is_remedial) as is_remedial'),DB::raw('max(created_at) as created_at'),'student_id')
            ->where('assignment_id',$taskid)
            ->with('students.schools.school_minimum_competency')
            ->with('students.student_detail')
            ->where('score', '<', $minimumCompetency)
            ->groupBy('student_id')
            ->orderBy('created_at')
            ->get();
        }else{
            $studentTask = StudentAssignment:://select(DB::raw('min(id) as id'),DB::raw('min(score) as score'),DB::raw('min(finish_at) as finish_at'),DB::raw('bool_or(is_remedial) as is_remedial'),DB::raw('max(created_at) as created_at'),'student_id')
            where('assignment_id',$taskid)
            ->with('students.student_detail')
            // ->groupBy('student_id')
            ->orderBy('created_at')
            ->get();
        }

        $students=[];
        $studentsAttempts=[];
        $attempt=[];
        foreach($studentTask as $row){
            switch ($row->status){
                case 'finish':
                    $status = 'Submitted on time';
                    break;
                case 'late':
                    $status = 'Late submitted';
                    break;
                case 'unbegin':
                    $status = 'Not submitted yet';
            }

            if(empty($students[$row->student_id])){
                $score = $row->score;
            }else if($students[$row->student_id]['high_score']==null){
                $score = $row->score;
            }else if($students[$row->student_id]['high_score'] > $row->score){
                $score = $students[$row->student_id]['high_score'];
            }else{
                $score = $row->score;
            }

            // Latest
            $latestScore = $row->score;

            $students[$row->student_id]=[
                'id' =>$row->student_id,
                'name' =>$row->students->name,
                'profile_picture' =>$row->students->student_detail->profile_picture,
                'high_score'=> $score,
                'last_score'=> $row->score,
                'status' => $status,
            ];

            $attempt[$row->student_id][] =$row->id;

            $studentsAttempts[$row->student_id][]= [
                "attempt" =>  Ordinal::number(count($attempt[$row->student_id])) . " attempt",
                "score" => $row->score,
                "finish_at" => $row->finish_at,
                "deadline" => $row->deadline,
            ];
        }
        $resultStudent=[];
        foreach($students as $row){
            $lastAttempt=end($studentsAttempts[$row['id']]);
            $remedial = $row['high_score'] < $minimumCompetency?true:false ;
            $row['have_replacement'] = $remedial===false?"":$lastAttempt['score']?false:true ;
            $row['replacement_deadline'] = $row['have_replacement']===false?null:$lastAttempt['deadline'];
            $row['attempts']= $studentsAttempts[$row['id']];
            $resultStudent[]=$row;
        }
        $result= [
            'title'=> $task->title,
            'learning_contract_main_topic' => $task->learning_contract_information->main_topic,
            'learning_contract_sub_topic' => $task->learning_contract_information->sub_topic,
            'minimum_competency' => $minimumCompetency,
            'total_point' => $task->total_point,
            'deadline' => Carbon::parse($task->deadline)->format('l, d F Y G:i'),//date("H:i d F Y",strtotime($task->deadline)),
            'students' => $resultStudent
        ];

        return Response::status('success')->result($result);

    }

    public function submitTaskRemedials(Request $request){
        $studentIds = $request->student_id;
        $taskId = $request->task_id;

        // Insert Student
        $studentAssignment = [];
        foreach ($studentIds as $studentId){
            $studentAssignment[] = [
                'student_id' => $studentId,
                'assignment_id' => $taskId,
                'status' => 'unbegin',
                'deadline' => $request->input('deadline')['date']." ".$request->input('deadline')['time'],
                'is_remedial' => true,
            ];
        }
        $studentTask = StudentAssignment::insert($studentAssignment);
        return Response::status('success')->code(201)->result([]);
    }
}
