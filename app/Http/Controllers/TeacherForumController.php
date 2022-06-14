<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\LearningContract;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\Test;
use App\Utils\Ordinal;
use App\Utils\Paginate;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TeacherForumController extends Controller
{
    public function info(Request $request){
        // Prerequisites
        $user = Auth::user();
        $teacherId =$user->teacher->id;
        $classroomId = $request->classroom_id;
        $subjectId = $request->subject_id;

        // Validate
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|numeric',
            'subject_id' => 'required|numeric'
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
        $schedule = Schedule::where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $teacherId)
            ->with('subjects')->with('classrooms')->with('teachers')
            ->first();

        // Validate is replacement
        if($schedule->classrooms->classroom_type == 'replacement'){
            $replacement = true;
        }
        else{
            $replacement = false;
        }

        $result = [
            'subject' => $schedule->subjects->name,
            'teacher' => $schedule->teachers->name,
            'classroom' => "Classroom ".$schedule->classrooms->name,
            'is_replacement' => $replacement
        ];

        return Response::status('success')->result($result);
    }

    public function today(Request $request){
        // Prerequisites
        $user = Auth::user();
        $teacher = $user->teacher;
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|numeric',
            'subject_id' => 'required|numeric'
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

        //Query
        $dateToday = Carbon::now()->toDateString();
        $todayMeet = Schedule::query()
            ->select(DB::raw('min(teacher_id) as teacher_id'), DB::raw('min(subject_id) as subject_id'),
                DB::raw('min(classroom_id) as classroom_id'), DB::raw('min(semester) as semester'),
                DB::raw('min(start_at) as start_at'), DB::raw('max(end_at) as end_at'), 'date')
            ->where('teacher_id', $teacher->id)
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->orderBy('date')
            ->groupBy('date')
            ->get();

        if (!$todayMeet){
            return Response::status('success')->code(204)->result("No Agendas");
        }

        $first = [];
        $i = 1;
        foreach ($todayMeet as $item) {
            $dataResult = [
                'name' => Ordinal::number($i++) . " Meeting",
                'teacher' => $item->teacher->name,
                'semester' => $item->semester,
                'day' => Carbon::parse($item->date)->format('l'),
                'date' => Carbon::parse($item->date)->format('d F Y'),
                'start_at' => Carbon::parse($item->start_at)->format('G:i'),
                'end_at' => Carbon::parse($item->end_at)->format('G:i'),
                'filter_date' => $item->date
            ];
            array_push($first, $dataResult);
        }

        //Today
        if (collect($first)->where('date', $dateToday)->first()){
            $final = collect($first)->where('date', $dateToday)->first();
            $final['day'] = "Today";
        }
        else{
            $final = collect($first)->where('filter_date', '>', $dateToday)->first();
            $final['day'] = Carbon::parse($final['filter_date'])->format('l');
        }
        return Response::status('success')->result($final);
    }

    public function list(Request $request){
        // Prerequisites
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');
        $lessonId = $request->input('lesson_id');
        $search = $request->input('q');

        // Validate
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
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

        // Student Classroom
        $classroom = Classroom::find($classroomId);
        foreach ($classroom->students as $student){
            $true = $student->pivot->where('is_active', true)->where('classroom_id', $classroomId)->first();
            $year = $true->school_year;
        }

        /// Query
        //Task
        $tasks = Assignment::query()
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->where('school_year', $year);

            if ($search != null){
               $tasks = $tasks->where('title', 'ilike', '%'.$search.'%');
            }

            $tasks = $tasks->get();

        // Test
        $tests = Test::with(['test_question_items' => function ($query){
                $query->with('question_item');
            }])
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->where('school_year', $year);

            if ($search != null){
                $tests = $tests->where('title', 'ilike', '%'.$search.'%');
            }

            $tests = $tests->get();

        // Foreach Data
        $result_task = [];
        $result_test = [];

        $id = 1;
        foreach ($tasks as $task){

            // Attachments
            $attachment = [];
            foreach ($task->medias as $media){
                $dataAttach = [
                    'file' => $media->file,
                    'extension' => $media->extension,
                    'name' => $media->name,
                    'size' => $media->size
                ];
                array_push($attachment, $dataAttach);
            }

            $dataTask = [
                'id' => $id ++,
                'assignment_id' => $task->id,
                'type' => 'assignment',
                'title' => $task->title,
                'description' => $task->description,
                'material' => $task->learning_contract_information->main_topic,
                'deadline' => Carbon::parse($task->deadline)->format('d F Y'),
                'total_point' => $task->total_point,
                'attachment' => $attachment,
                'filter_date' => $task->created_at->toDateString(),
                'filter_type' => 'c',
                'filter_time' => $task->created_at->toTimeString(),
            ];
            array_push($result_task, $dataTask);
        }

        foreach ($tests as $test){
            $question_item_list = "";
            $multipleItem = 0;
            $essayItem = 0;
            $checkboxItem = 0;
            $mixAndMatchItem = 0;
            $trueOrFalseItem = 0;
            foreach ($test->test_question_items as $item){
                $question_item = $item->question_item;
                switch ($question_item->question_item_type){
                    case "multiple":
                        $multipleItem++;
                        break;
                    case "essay":
                        $essayItem++;
                        break;
                    case "mix_and_match":
                        $raw = $question_item->select(DB::raw('min(id) as id'), DB::raw('min(question_item_type) as question_item_type'), 'mix_and_match_group')
                            ->where('question_item_type', 'mix_and_match')->groupBy('mix_and_match_group')
                            ->get();
                        $mixAndMatchItem = count($raw);
                        break;
                    case "true_or_false":
                        $trueOrFalseItem++;
                        break;
                    case "checkbox":
                        $checkboxItem++;
                        break;
                    default:
                        break;
                }
            }

            $multipleQuestions = $multipleItem > 1 ? "questions" : "question";
            $essayIQuestions = $essayItem > 1 ? "questions" : "question";
            $checkboxQuestions = $checkboxItem > 1 ? "questions" : "question";
            $mixAndMatchQuestions = $mixAndMatchItem > 1 ? "questions" : "question";
            $trueOrFalseQuestions = $trueOrFalseItem > 1 ? "questions" : "question";

            if ($multipleItem)
                $question_item_list = "$question_item_list $multipleItem multiple choice $multipleQuestions,";

            if ($checkboxItem)
                $question_item_list =  "$question_item_list $checkboxItem checkbox $essayIQuestions,";

            if ($mixAndMatchItem)
                $question_item_list =  "$question_item_list $mixAndMatchItem mix and match $checkboxQuestions,";

            if ($trueOrFalseItem)
                $question_item_list =  "$question_item_list $trueOrFalseItem true or false $mixAndMatchQuestions,";

            if ($essayItem)
                $question_item_list =  "$question_item_list $essayItem essay $trueOrFalseQuestions,";

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

            $desc = "This Test has $question_item_list";
            if ($desc == "This Test has "){
                $desc = "This Test doesn't have any question";
            }

            $dataTest = [
                'id' => $id++,
                'test_id' => $test->id,
                'type' => 'test',
                'title' => $test->title,
                'description' => $desc,
                'material' => $test->learning_contract_information->main_topic,
                'date' => Carbon::parse($test->date)->format('d F Y'),
                'start_at' => Carbon::parse($test->start_at)->toTimeString(),
                'end_at' => Carbon::parse($test->end_at)->toTimeString(),
                'total_point' => $test->total_point,
                'filter_date' => $test->created_at->toDateString(),
                'filter_type' => 'b',
                'filter_time' => $test->created_at->toTimeString(),
            ];
            array_push($result_test, $dataTest);
        }

        // Merging Data
        $merge = collect(array_merge($result_task, $result_test))
            ->sortByMulti(['filter_date' => 'DESC', 'filter_type' => 'ASC', 'filter_time' => 'DESC']);

        // Paginate
        $page = Paginate::paginator($merge, 10);

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

    public function semester(Request $request){
        $classroomId = $request->classroom_id;
        $classroom = Classroom::find($classroomId);

        if (!$classroom){
            return Response::status('failure')->result("Couldn't find any matching classroom");
        }

        switch($classroom->grade){
            case '1':
            case '7':
            case '10':
                $semester = [1,2];
                break;
            case '2':
            case '8':
            case '11':
                $semester = [3,4];
                break;
            case '3':
            case '9':
            case '12':
                $semester = [5,6];
                break;
            case  '4':
                $semester = [7,8];
                break;
            case  '5':
                $semester = [9,10];
                break;
            case  '6':
                $semester = [11,12];
                break;
            default:
                break;
        }

        $result = [];
        $i = 1;
        foreach ($semester as $item) {
            $dataResult = [
                'id' => $i++,
                'semester' => $item
            ];
            array_push($result, $dataResult);
        }

        return Response::status('success')->result($result);
    }

    public function learningMaterial(Request $request){
        $user = Auth::user();
        $teacherId = $user->teacher->id;
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|numeric',
            'subject_id' => 'required|numeric'
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

        // Query current School Year
        $classroom = Classroom::find($classroomId);
        foreach ($classroom->students as $student){
            $true = $student->pivot->where('is_active', true)->first();
            $year = $true->school_year;
        }

        // Query
        $lc = LearningContract::query()
            ->with(['learning_contract_informations' => function($q){
                $q->select(DB::raw('min(id) as id'), 'learning_contract_id', DB::raw('min(main_topic) as main_topic'));
                $q->groupBy('main_topic', 'learning_contract_id');
            }])
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $teacherId)
            ->where('school_year', $year)
            ->orderBy('id')
            ->get();

        foreach ($lc as $item){
            foreach ($item->learning_contract_informations as $lci){
                $result[] = [
                    'learning_contract_information_id' => $lci->id,
                    'main_topic' => $lci->main_topic
                ];
            }
        }

        return Response::status('success')->result($result);
    }

    public function create(Request $request){
        $mediaId = $request->input('media_id');
        $classroomId = $request->input('classroom_id');
        $lessonId = $request->input('lesson_id');
        $learningContractInformationId = $request->input('learning_contract_information_id');

        // School Year
        $classroom = Classroom::find($classroomId);
        foreach ($classroom->students as $student){
            $true = $student->pivot->where('is_active', true)->first();
            $year = $true->school_year;
        }

        if(!$lessonId){
            $type = $classroom->classroom_type;
        }
        else{
            $type = $classroom->classroom_type.'-additional';
        }

        $newLesson = new Lesson();
        $newLesson->title = $request->title;
        $newLesson->description = $request->description;
        $newLesson->lesson_type = $type;
        $newLesson->school_year = $year;
        $newLesson->learning_contract_information_id = $learningContractInformationId;
        $newLesson->save();
        $newLesson->medias()->attach($mediaId);

        return Response::status('success')->code(201)->result(null);
    }

    public function update(Request $request){
        // Prerequisites
        $user = Auth::user();
        $teacherId = $user->teacher->id;
        $lessonId = $request->input('lesson_id');
        $scheduleId = $request->input('schedule_id');
        $mediaId = $request->input('media_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'numeric',
            'schedule_id' => 'numeric'
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
            $schedule = Schedule::find($scheduleId);

            // Validate
            if ($schedule == null){
                return Response::status('failure')->result(["Couldn't find lesson"]);
            }

            // Get all exact same object
            $schedules = Schedule::all()
                ->where('date', $schedule->date)
                ->where('teacher_id', $schedule->teacher_id)
                ->where('subject_id', $schedule->subject_id)
                ->where('classroom_id', $schedule->classroom_id)
                ->where('learning_contract_information_id', $schedule->learning_contract_information_id)
                ->where('school_year', $schedule->school_year)
                ->where('semester', $schedule->semester)
                ->where('schedule_type', $schedule->schedule_type);

            // Update
            foreach ($schedules as $item){
                $updateSchedule = Schedule::find($item->id);
                $updateSchedule->medias()->sync($mediaId);
                $updateSchedule->push();
            }

        return Response::status('success')->result(null);
    }

    public function delete(Request $request){
        $lessonId = $request->lesson_id;

        // Validate
        $validator = Validator::make($request->all(), [
            'lesson_id' => 'required|numeric',
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

        $lesson = Lesson::find($lessonId);
        $lesson->delete();

        return Response::status('success')->result(null);
    }
}
