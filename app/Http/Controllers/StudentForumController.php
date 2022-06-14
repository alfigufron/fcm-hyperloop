<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\QuestionItem;
use App\Models\SchoolMinimumCompetency;
use App\Models\Subject;
use Auth;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\VideoMeet;
use App\Models\VideoMeetParticipant;
use App\Utils\Ordinal;
use App\Utils\Paginate;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentForumController extends Controller
{
    /**
     * STUDENT FORUM HEADER
     *
     */
    public function header(Request $request){
        $user           = Auth::user();
        $student        = $user->student;
        $subjectId      = $request->query('subject_id');
        $classroomId    = $request->query('classroom_id');

        $classroom = Classroom::where('id', $classroomId)->whereHas('classroom_students', function($q) use($student){
            $q->where('student_id', $student->id);
        })->with(['classroom_students' => function($q) use($student){
            $q->where('student_id', $student->id);
        }])->first();

        $schedules = Schedule::where('classroom_id', $classroomId)
        ->where('subject_id', $subjectId)
        ->where('school_year', $classroom->classroom_students[0]->school_year)
        ->with(['subject', 'teacher'])->get();

        // Check study session existence
        $studySession = null;
        $todaySchedule = $schedules->where('date', Carbon::now())->first();
        if($todaySchedule){
            $schedule = $todaySchedule;
            $videoMeet = $todaySchedule->video_meet;

            if($videoMeet && $videoMeet->finish_at == null){
                $studySession = $videoMeet->link;
            }
        }else{
            $schedule = $schedules[0];
        }

        $result = [
            'classroom' => [
                'name' => $classroom->name,
                'grade' => $classroom->grade
            ],
            'subject' => $schedule->subject->name,
            'teacher' => $schedule->teacher->name,
            'study_session' => $studySession
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * STUDENT FORUM LIST
     *
     */
    public function list(Request $request){
        $user           = Auth::user();
        $student        = $user->student;
        $subjectId      = $request->query('subject_id');
        $classroomId    = $request->query('classroom_id');
        $search         = $request->query('q');

        $classroom = Classroom::where('id', $classroomId)->whereHas('classroom_students', function($q) use($student){
            $q->where('student_id', $student->id);
        })->with(['classroom_students' => function($q) use($student){
            $q->where('student_id', $student->id);
        }])->with(['schools' => function($query){
            $query->first()->with('school_minimum_competency');
        }])->first();


        if(!$classroom){
            return Response::status('failure')->code(422)->result(["Data not found!"]);
        }

        $schoolYear         = $classroom->classroom_students[0]->school_year;

        /**
         * GET SEVERAL DATA
         * SCHEDULES, based on      : SCHOOL YEAR, SUBJECT, DATE <= +1 day from today
         * ASSIGNMENTS, based on    : SCHOOL YEAR, SUBJECT
         * TESTS, based on          : SCHOOL YEAR, SUBJECT
         * LESSONS, based on        : SCHOOL YEAR, SUBJECT, STUDENT LESSON
         *
         */
        $data = Classroom::where('id', $classroom->id)->with(['schedules' => function($q) use($schoolYear, $subjectId, $student){
            $q->where('school_year', $schoolYear);
            $q->where('subject_id', $subjectId);
            $q->where('date', '<=', Carbon::now()->addDays(1));
            $q->with(['learning_contract_information', 'teacher', 'medias']);
        }])->with(['assignments' => function($q) use($search, $schoolYear, $subjectId, $student){
            if ($search != null){
                $q->where('title', 'ilike', '%'.$search.'%');
            }
            $q->where('school_year', $schoolYear);
            $q->where('subject_id', $subjectId);
            $q->whereHas('student_assignments', function($q) use($student){
                $q->select(DB::raw('max(id) as id'), 'assignment_id', 'student_id',
                    DB::raw('min(deadline) as deadline'), DB::raw('min(status) as status'),
                    DB::raw('bool_or(is_remedial) as is_remedial'), DB::raw('max(created_at) as created_at'));
                $q->orderByDesc('created_at');
                $q->groupBy('assignment_id', 'student_id');
                $q->where('student_id', $student->id);
            });
            $q->with(['learning_contract_information', 'student_assignments.student_assignment_attempts']);
        }])->with(['tests' => function($q) use($schoolYear, $subjectId, $student, $search){
            if ($search != null){
                $q->where('title', 'ilike', '%'.$search.'%');
            }
            $q->where('school_year', $schoolYear);
            $q->where('subject_id', $subjectId);
            $q->with(['learning_contract_information', 'test_question_items']);
            $q->with(['student_tests' => function($q) use($student){
                $q->select(DB::raw('max(id) as id'), 'test_id', 'student_id',
                    DB::raw('min(start_at) as start_at'), DB::raw('min(end_at) as end_at'),
                    DB::raw('min(score) as score'), DB::raw('min(status) as status'),
                    DB::raw('bool_or(is_remedial) as is_remedial'), DB::raw('max(created_at) as created_at'),
                    DB::raw('max(student_start_at) as student_start_at'));
                $q->orderByDesc('created_at');
                $q->groupBy('test_id', 'student_id');
                $q->where('student_id', $student->id);
            }]);
        }])->with(['schools' => function($query){
            $query->first()->school_minimum_competency;
        }])->first();

        $list = [];

        $schedules          = $data->schedules->groupBy('learning_contract_information_id');
        $assignments        = $data->assignments;
        $tests              = $data->tests;
        $id = 1;
        if ($student->schools()->first()->name == 'SD IIBS' || $student->schools()->first()->name == 'SMP IIBS'){
            $minimumCompetency = Subject::where('id', $subjectId)->first()->minimum_competency;
        }
        else{
            $minimumCompetency = SchoolMinimumCompetency::where('school_id', $student->schools()->first()->id)->first()->value;
        }

        /**
         * Assignments modification
         *
         */
        foreach($assignments as $assignment){

            // Student Assignment Data
            $studentAssignment = $assignment->student_assignments->where('student_id', $student->id)->sortByDesc('id')->first();

            // is Remedial
            $isRemedial = $studentAssignment->is_remedial;
            if ($isRemedial == null){
                $isRemedial = false;
            }

            // Remedial
//            $remedial = false;
//            if($assignment->student_assignments->count() > 0){
//                $score = $assignment->student_assignments[0]->score;
//                if($score !== null){
//                    $remedial = $score < $minimumCompetency;
//                }
//            }

            // Attachments
            $attachment = [];
            foreach ($assignment->medias as $media){
                $dataAttach = [
                    'extension' => $media->extension,
                    'file' => $media->file,
                    'name' => $media->name,
                    'size' => $media->size
                ];
                array_push($attachment, $dataAttach);
            }

            // Status
            $status = null;
            switch ($studentAssignment->status){
                case 'finish':
                case 'finished':
                case 'late':
                    $status = 'You have submitted the task';
                    break;
                case 'unbegin':
                    $status = "You haven't submitted the task";
                    break;
                default :
                    break;
            }

            // Student Progress for Mobile
            switch($studentAssignment->status){
                case 'finish':
                case 'finished':
                    $progress = "You submitted the task on time";
                    break;
                case 'late':
                    $progress = "You submitted the task late";
                    break;
                case 'unbegin':
                    $progress = "You haven't submitted the task";
                    break;
            }

            // For Filter
            $isSubmitted = $studentAssignment->status == 'finish';

            $dataPush = [
                'id' => $id++,
                'assignment_id' => $assignment->id,
                'student_assignment_id' => $studentAssignment->id,
                'type' => "assignment",
                'title' => $assignment->title,
                'description' => $assignment->description,
                'material' => $assignment->learning_contract_information->main_topic,
                'deadline' => Carbon::parse($studentAssignment->deadline)->format('l, d F Y G:i'),
                'total_point' => $assignment->total_point,
                'attachment' => $attachment,
                'status' => [
                    'raw' => $studentAssignment->status,
                    'formatted' => $status,
                ],
                'score' => $studentAssignment->score,
                'is_remedial' => $isRemedial,
                'progress' => $progress,
                'is_submitted' => $isSubmitted,
                'filter_date' => $assignment->created_at->toDateTimeString(),
                'filter_type' => 'c',
            ];

            array_push($list, $dataPush);
        }

        /**
         * Tests modification
         *
         */
        foreach($tests as $test){
            // Description
            $multiple = 0;
            $essay = 0;
            $true_or_false = 0;
            $mix_and_match = 0;
            $checkbox = 0;

            foreach($test->test_question_items as $data){
                $question_item = $data->question_item;
                switch($data->question_item->question_item_type){
                    case 'multiple':
                        $multiple++;
                        break;
                    case 'essay':
                        $essay++;
                        break;
                    case 'true_or_false':
                        $true_or_false++;
                        break;
                    case 'mix_and_match':
                        $raw = QuestionItem::query()
                            ->whereHas('test_question_item', function ($q) use ($test){
                                $q->where('test_question_items.test_id', $test->id);
                            })
                            ->select(DB::raw('min(id) as id'), DB::raw('min(question_item_type) as question_item_type'), 'mix_and_match_group')
                            ->where('question_item_type', 'mix_and_match')
                            ->groupBy('mix_and_match_group')->get();

                        $mix_and_match = count($raw);
                        break;
                    case 'checkbox':
                        $checkbox++;
                        break;
                }
            }

            $question_item_list = "";
            $multipleQuestions = $multiple > 1 ? "questions" : "question";
            $essayIQuestions = $checkbox > 1 ? "questions" : "question";
            $checkboxQuestions = $mix_and_match > 1 ? "questions" : "question";
            $mixAndMatchQuestions = $true_or_false > 1 ? "questions" : "question";
            $trueOrFalseQuestions = $essay > 1 ? "questions" : "question";

            if ($multiple)
                $question_item_list = "$question_item_list $multiple multiple choice $multipleQuestions,";

            if ($checkbox)
                $question_item_list = "$question_item_list $checkbox checkbox $essayIQuestions,";

            if ($mix_and_match)
                $question_item_list = "$question_item_list $mix_and_match mix and match $checkboxQuestions,";

            if ($true_or_false)
                $question_item_list = "$question_item_list $true_or_false true or false $mixAndMatchQuestions,";

            if ($essay)
                $question_item_list = "$question_item_list $essay essay $trueOrFalseQuestions,";


            if ($question_item_list) {
                $question_item_list = substr_replace($question_item_list, '.', -1);

                $str_pos = strrpos($question_item_list, ',');
                if ($str_pos !== false)
                    $question_item_list = substr_replace(
                        $question_item_list,
                        ' &',
                        $str_pos,
                        strlen(',')
                    );
            }

            $description = "This test has$question_item_list";
            if ($description === "This test has"){
                $description = "This Test doesn't have any question";
            }
            // End of Description

            // Remedial
//            $remedial = false;
//            if($test->student_tests->count() > 0){
//                $score = $test->student_tests[0]->score;
//                if($score !== null){
//                    $remedial = $score < $minimumCompetency ? true : false;
//                }
//            }

            // Student Test
            $studentTest = $test->student_tests->where('student_id', $student->id)->sortByDesc('id')->first();

            // Remed
            $isRemedial = $studentTest->is_remedial;
            if ($isRemedial == null){
                $isRemedial = false;
            }

            // Student Progress for Mobile
            switch($studentTest->status){
                case 'finish':
                case 'finished':
                    $progress = 'You have finished the test';
                    break;
                case 'ongoing':
                    $progress = 'You are currently doing the test';
                    break;
                case 'pending':
                    $progress = "You haven't started the test yet";
                    break;
                default:
                    break;
            }

            // For Filter
            $isSubmitted = $studentTest->status == 'finish';

            $dataPush = [
                'id' => $id++,
                'test_id' => $test->id,
                'student_test_id' => $studentTest->id,
                'type' => "test",
                'title' => $test->title,
                'description' => $description,
                'material' => $test->learning_contract_information->main_topic,
                'duration' => $test->duration,
                'start_at' => [
                    'raw' => $studentTest->start_at,
                    'formatted' => Carbon::parse($studentTest->start_at)->format('l, d F Y G:i'),
                ],
                'end_at' => [
                    'raw' => $studentTest->end_at,
                    'formatted' => Carbon::parse($studentTest->end_at)->format('l, d F Y G:i'),
                ],
                'total_point' => $test->total_point,
                'student_start_at' => $studentTest->student_start_at,
                'status' => [
                    'raw' => $studentTest->status,
                    'formatted' => $progress
                ],
                'score' => $studentTest->score,
                'is_remedial' => $isRemedial,
                'is_submitted' => $isSubmitted,
                'filter_date' => $test->created_at->toDateTimeString(),
                'filter_type' => 'b',
            ];

            array_push($list, $dataPush);
        }

        $list = collect($list);
        $list = $list->sortByMulti([
            'is_submitted' => 'ASC',
            'filter_date' => 'DESC',
            'filter_type' => 'ASC',
        ])->toArray();

        // Pagination
        $paginate = Paginate::paginator($list);
        $pagination = [
            'total_page' => $paginate->lastPage(),
            'total_records' => $paginate->total(),
            'current_page' => $paginate->currentPage()
        ];

        $result = [
            'minimum_competency' => $minimumCompetency,
            'pagination' => $pagination,
            'records' => array_values($paginate->items())
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * STUDENT FORUM STUDY SESSION JOIN
     *
     */
    public function join(Request $request){
        $user = Auth::user();
        $link = $request->study_session;

        if(!$link){
            return Response::status('failure')->code(422)->result(["Study session is required!"]);
        }

        // Search video meet based on today's schedule
        $videoMeet = VideoMeet::where('link', $link)->whereHas('schedule', function($q){
            $q->where('date', Carbon::now());
        })->with('schedule')->first();

        if(!$videoMeet){
            return Response::status('failure')->code(422)->result(["Study session has finished!"]);
        }

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * STUDENT FORUM STUDY SESSION HANG UP
     *
     */
    public function hangUp(Request $request){
        $displayName = $request->display_name;
    }

    /**
     * STUDENT SEMESTER
     *
     */
    public function semester(Request $request) {
        $classroom_id = $request->classroom_id;

        $classroom = Classroom::find($classroom_id);

        $semester = Helper::getSemesterByClass($classroom->grade);

        $result = [];
        foreach ($semester as $key => $item) {
            $result[$key] = [
                'id' => $key+1,
                'grade' => $classroom->grade,
                'semester' => $item
            ];
        }

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * STUDENT NEXT MEETING
     *
     */
    public function nextMeeting(Request $request) {
        $user = Auth::user();
        $student = $user->student;

        $classroom_id = $request->query('classroom_id');
        $subject_id = $request->query('subject_id');

        $classroom = Classroom::query()
            ->with(['schedules' => function ($q) {
                $q->orderBy('date', 'ASC');
            }])
            ->whereHas('classroom_students', function($q) use($student){
                $q->where('student_id', $student->id);
            })->with(['classroom_students' => function($q) use($student){
                $q->where('student_id', $student->id);
//                $q->where('is_active', true);
            }])->with(['schools' => function($query){
                $query->first()->with('school_minimum_competency');
            }])
            ->where('id', $classroom_id)->first();

        $school_year = $classroom->classroom_students[0]->school_year;

        $schedule_by_dates = Schedule::select(
                DB::raw('min(start_at) as start_at'),
                DB::raw('max(end_at) as end_at'),
                DB::raw('min(teacher_id) as teacher_id'),
                DB::raw('min(semester) as semester'),
                'date'
            )
            ->where('subject_id', $subject_id)
            ->where('classroom_id', $classroom_id)
            ->where('school_year', $school_year)
            ->orderBy('date')
            ->groupBy('date')
            ->get();

        if ($schedule_by_dates->where('date', '>=',  Carbon::now())->isEmpty()){
            return Response::status('success')->code(204)->result("No Agendas");
        }

        $index = 0;
        $index_meeting = 1;
        $result = [];
        $date_now = Carbon::now();
        foreach ($schedule_by_dates as $schedules) {
            $schedule_date = Carbon::parse($schedules->date);

            if ($schedule_date >= $date_now) {
                $result[$index] = [
                    'name' => Ordinal::number($index_meeting) . " Meeting",
                    'day' => Carbon::parse($schedules->date)->isoFormat('dddd'),
                    'date' => Carbon::parse($schedules->date)->format('d F Y'),
                    'semester' => $schedules->semester,
                    'start_at' => Carbon::parse($schedules->start_at)->isoFormat('HH:mm'),
                    'end_at' => Carbon::parse($schedules->end_at)->isoFormat('HH:mm'),
                    'teacher' => $schedules->teacher->name
                ];

                $index++;
            }

            $index_meeting++;
        }

        return Response::status('success')->code(200)->result($result[0]);
    }
}
