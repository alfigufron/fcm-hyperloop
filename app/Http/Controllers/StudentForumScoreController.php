<?php

namespace App\Http\Controllers;

use App\Utils\Ordinal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Utils\Response;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\Test;
use App\Utils\Paginate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class StudentForumScoreController extends Controller
{
    /**
     * Header
     *
     */
    public function header(Request $request) {
        // Validation
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        $subject = Subject::find($request->subject_id);

        $second = null;
        if ($request->query('test_id') !== null) {
            $test = Test::find($request->test_id);

            $second = $test->learning_contract_information->main_topic;
        }

        if ($request->query('task_id') !== null) {
            $task = Assignment::find($request->task_id);
            $second = $task->learning_contract_information->main_topic;
        }

        $result = [
            'first' => $subject->name,
            'second' => $second
        ];

        return Response::status('success')->result($result);
    }

    public function scoreList(Request $request) {
        // Params
        $subjectId = $request->query('subject_id');
        $classroomId = $request->query('classroom_id');
        $search = $request->query('q');

        // Validate Params
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|exists:classrooms,id',
            'subject_id' => 'required|exists:subjects,id'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        // User
        $user = Auth::user();
        $student = $user->student;

        // Main Query
        $classroom = Classroom::query()
            ->with(['tests' => function($query) use($subjectId, $student, $search){
                if ($search != null){
                    $query->where('title', 'ilike', '%'.$search.'%');
                }

                $query->where('subject_id', $subjectId);
                $query->orderBy('created_at');
                $query->with(['student_tests' => function($q) use ($student){
                    $q->where('student_id', $student->id);
                    $q->orderBy('created_at');
                }]);
            }])
            ->with(['assignments' => function($query) use($search, $subjectId, $student){
                if ($search != null){
                    $query->where('title', 'ilike', '%'.$search.'%');
                }

                $query->where('subject_id', $subjectId);
                $query->orderBy('created_at');
                $query->with(['student_assignments' => function($q) use($student){
                    $q->where('student_id', $student->id);
                    $q->orderBy('created_at');
                }]);
            }])
            ->where('id', $classroomId)
            ->first();

        // Get school or dorm relation and its minimum competency
        if ($classroom->schools()->exists()){
            $school = $classroom->schools()->first();
            $classMinimumCompetency = $school->school_minimum_competency->value;
        }
        elseif ($classroom->dormitories()->exists()){
            $dorm = $classroom->dormitories()->first();
            $classMinimumCompetency = $dorm->dormitory_minimum_competency->value;
            $school = $dorm->schools;
        }

        switch ($school->levels->slug){
            case 'SD IIBS':
            case 'SMP IIBS':
                $val = $dorm->subjects()->where('subject_id', $subjectId)->first()->minimum_competency;
                break;
            default:
                $val = $classMinimumCompetency;
                break;
        }

        // Foreach data
        $id = 1;
        $rawRecord = [];
        foreach($classroom->assignments as $item){
            $studentTask = $item->student_assignments->last();

            // Status Collect
            switch ($studentTask->status){
                case 'unbegin':
                    $status = "not submitted yet";
                    break;
                case 'finish':
                    $status = "submitted on time";
                    break;
                case 'late':
                    $status = "late submitted";
                    break;
                default:
                    $status = null;
                    break;
            }

            // Remedial
            switch ($studentTask->is_remedial){
                case null:
                case false:
                    $remedial = false;
                    break;
                case true:
                    $remedial = true;
                    break;
                default:
                    $remedial = null;
                    break;
            }

            // Score
            $i = 1;
            $score = [];
            $maxScore = null;
            foreach ($item->student_assignments as $student_assignment){
                if ($studentTask->score != null) {
                    $score[] = [
                        'score' => $student_assignment->score,
                        'attempt' => Ordinal::number($i++) . " attempt",
                        'finished_at' => $student_assignment->finish_at
                    ];

                    $maxScore = collect($score)->max()['score'];
                }
                else{
                    $maxScore = null;
                }
            }

            // Student finish
            if($studentTask->finish_at != null){
                $finishAt = Carbon::parse($studentTask->finish_at)->format('l, d F Y G:i');
            }
            else{
                $finishAt = null;
            }

            $dataTask = [
                'id' => $id++,
                'type' => 'task',
                'task_id' => $item->id,
                'title' => $item->title,
                'material' => $item->learning_contract_information->main_topic,
                'deadline' => Carbon::parse($studentTask->deadline)->format('l, d F Y G:i'),
                'finished_at' => $finishAt,
                'scores' => $score,
                'highest_score' => $maxScore,
                'test_score' => $item->total_point,
                'status' => $status,
                'is_remedial' => $remedial,
                'created_at' => Carbon::parse($item->created_at)->toDateTimeString(),
            ];

            array_push($rawRecord, $dataTask);
        }

        foreach($classroom->tests as $item){
            $studentTest = $item->student_tests->first();

            // Status Collect
            switch ($studentTest->status){
                case 'pending':
                    $status = "not submitted yet";
                    break;
                case 'ongoing':
                    $status = "ongoing";
                    break;
                case 'finish':
                    $status = "submitted";
                    break;
                default:
                    $status = null;
                    break;
            }

            // Remedial
            switch ($studentTest->is_remedial){
                case null:
                case false:
                    $remedial = false;
                    break;
                case true:
                    $remedial = true;
                    break;
                default:
                    $remedial = null;
                    break;
            }

            // Score
            $i = 1;
            $score = [];
            $maxScore = null;
            foreach ($item->student_tests as $student_tests){
                if ($student_tests->score != null){
                    $score[] = [
                        'score' => $student_tests->score,
                        'attempt' => Ordinal::number($i++) . " attempt",
                        'finished_at' => $student_tests->student_end_at
                    ];

                    $maxScore = collect($score)->max()['score'];
                }
                else{
                    $maxScore = null;
                }
            }

            // Student finish
            if ($studentTest->student_end_at != null){
                $finishAt = Carbon::parse($studentTest->student_end_at)->format('l, d F Y G:i');
            }
            else{
                $finishAt = null;
            }

            $dataTest = [
                'id' => $id++,
                'type' => 'test',
                'task_id' => $item->id,
                'title' => $item->title,
                'material' => $item->learning_contract_information->main_topic,
                'start_at' => Carbon::parse($studentTest->start_at)->format('l, d F Y G:i'),
                'end_at' => Carbon::parse($studentTest->end_at)->format('l, d F Y G:i'),
                'finished_at' => $finishAt,
                'scores' => $score,
                'highest_score' => $maxScore,
                'status' => $status,
                'is_remedial' => $remedial,
                'created_at' => Carbon::parse($item->created_at)->toDateTimeString(),
            ];

            array_push($rawRecord, $dataTest);
        }

        // Determine result based on request param type
        $finalRecord = collect($rawRecord)->sortByMulti(['created_at' => 'DESC', 'type' => 'DESC']);
        switch ($request->query('type')) {
            case 'test':
                if ($request->query('remedial') == true) {
                    $finalRecord = $finalRecord->where('highest_score', '<', $val)->where('type', 'test')->toArray();
                } else {
                    $finalRecord = $finalRecord->where('type', 'test')->toArray();
                }
                break;
            case 'task':
                if ($request->query('remedial') == true) {
                    $finalRecord = $finalRecord->where('highest_score', '<', $val)->where('type', 'task')->toArray();
                } else {
                    $finalRecord = $finalRecord->where('type', 'task')->toArray();
                }
                break;
            default :
                if ($request->query('remedial') == true) {
                    $finalRecord = $finalRecord->where('highest_score', '<', $val)->toArray();
                } else {
                    $finalRecord = $finalRecord->toArray();
                }
                break;
        }

        // Paginate
        $page = Paginate::paginator($finalRecord);

        // Result
        $result = [
            'paginate' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage()
            ],
            'records' => array_values($page->items())
        ];

        return Response::status('success')->result($result);
    }
}
