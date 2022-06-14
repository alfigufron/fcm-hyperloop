<?php

namespace App\Http\Controllers;

use App\Models\QuestionItem;
use App\Models\Student;
use App\Models\Subject;
use App\Utils\FileSize;
use Auth;
use App\Models\Media;
use App\Models\SchoolMinimumCompetency;
use App\Models\StudentTest;
use App\Models\StudentTestAnswer;
use App\Models\Test;
use App\Models\TestQuestionItem;
use App\Models\StudentTestQuestionItem;
use App\Mail\StudentTest\StudentFinishTestMailer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Validator;

class StudentTestController extends Controller
{
    /**
     * TEST
     *
     */
    public function test(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $classroomId = $request->query('classroom_id');
        $subjectId = $request->query('subject_id');

        $tests = Test::where('classroom_id', $classroomId)->where('subject_id', $subjectId)
            ->whereHas('student_tests', function($q){
                $q->whereIn('status', ['pending', 'ongoing']);
            })->get();

        $result = [];
        if(count($tests) > 0){
            foreach($tests as $test){
                $dataPush = [
                    'name' => $test->title,
                    'schedule' => Carbon::parse($test->start_at)->format('l, d F Y'),
                ];

                array_push($result, $dataPush);
            }
        }

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * FORUM TEST
     *
     */
    public function forumTest(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $classroom = $request->query('classroom_id');
        $subjectId = $request->query('subject_id');
        $search = $request->input('q');

        if ($student->schools()->first()->name == 'SD IIBS' || $student->schools()->first()->name == 'SMP IIBS'){
            $minimumCompetency = Subject::where('id', $subjectId)->first()->minimum_competency;
        }
        else{
            $minimumCompetency = SchoolMinimumCompetency::where('school_id', $student->schools()->first()->id)->first()->value;
        }

        // Get School Year
        $year = $student->classrooms()->where('classroom_type', 'regular')->wherePivot('is_active', true)->first()->pivot->school_year;

        $tests = Test::where('classroom_id', $classroom)->where('subject_id', $subjectId)
            ->with(['student_tests' => function($q) use($student){
                $q->select(DB::raw('max(id) as id'), 'test_id', 'student_id',
                    DB::raw('max(start_at) as start_at'), DB::raw('max(end_at) as end_at'),
                    DB::raw('max(status) as status'), DB::raw('bool_or(is_remedial) as is_remedial'),
                    DB::raw('max(score) as score'), DB::raw('max(created_at) as created_at'),
                    DB::raw('max(student_start_at) as student_start_at'));
                $q->orderBy('created_at');
                $q->groupBy('student_id', 'test_id');
                $q->where('student_id', $student->id);
            }])
            ->orderByDesc('created_at')
            ->where('school_year', $year)
            ->with('test_question_items.question_item');

        if ($search != null){
            $tests = $tests->where('title', 'ilike', '%'.$search.'%')->paginate(10);
        }
        else{
            $tests = $tests->paginate(10);
        }

        // Records
        $records = [];
        foreach($tests->items() as $test){
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

            $description = "The test has$question_item_list";
            if ($description == 'The test has'){
                $description = "This test doesn't have any question";
            }

            // End of Description

            // Remedial
//            $remedial = false;
//            if($test->student_tests->count() > 0){
//                $score = $test->student_tests[0]->score;
//                if($score !== null){
//                    $remedial = $score < $minimumCompetency->value ? true : false;
//                }
//            }

            // Is remedial
            $studentTest = $test->student_tests->first();
            if ($studentTest->is_remedial === null){
                $remed = false;
            }
            else{
                $remed = $studentTest->is_remedial;
            }

            // Progress
            $status = null;
            switch($studentTest->status){
                case 'finish':
                case 'finished':
                    $status = 'You have finished the test';
                    break;
                case 'ongoing':
                    $status = 'You are currently doing the test';
                    break;
                case 'pending':
                    $status = "You haven't started the test yet";
                    break;
                default:
                    break;
            }

            $isSubmitted = $studentTest->status == 'finish';

            $dataPush = [
                'test_id' => $test->id,
                'student_test_id' => $studentTest->id,
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
                    'formatted' => Carbon::parse($studentTest->start_at)->format('l, d F Y G:i'),
                ],
                'total_point' => $test->total_point,
                'student_start_at' => $studentTest->student_start_at,
                'status' => [
                    'raw' => $studentTest->status,
                    'formatted' => $status,
                ],
                'score' => $studentTest->score,
                'is_remedial' => $remed,
                'is_submitted' => $isSubmitted
            ];

            array_push($records, $dataPush);
        }

        $finishedRecords = collect($records)->sortByMulti([
            'is_submitted' => 'ASC',
        ])->toArray();

        // Pagination
        $pagination = [
            'total_page' => $tests->lastPage(),
            'total_records' => $tests->total(),
            'current_page' => $tests->currentPage(),
        ];

        $result = [
            'minimum_competency' => $minimumCompetency,
            'pagination' => $pagination,
            'records' => array_values($finishedRecords),
        ];

        return Response::status('success')->code(200)->result($result);
    }

    public function testDetail(Request $request){
        // Prerequisites
        $user = Auth::user();
        $student = $user->student;
        $testId = $request->input('test_id');

        // Query
        $test = Test::query()
            ->whereHas('student_tests', function ($query) use ($student) {
                $query->where('student_tests.student_id', $student->id);
            })
            ->with(['test_question_items' => function($query){
                $query->with('question_item');
            }])
            ->with(['student_tests' => function($query) use ($student) {
                $query->where('student_id', $student->id);
                $query->orderByDesc('id');
            }])
            ->where('id', $testId)
            ->first();

        if (!$test){
            return Response::status('failure')->result("Not Allowed!")->setStatusCode(403);
        }

        // Test Description
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
                        ->whereHas('test_question_item', function ($q) use ($testId){
                            $q->where('test_question_items.test_id', $testId);
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

        $description = "The test has$question_item_list";
        if ($description == 'The test has'){
            $description = "This test doesn't have any question";
        }

        $student_test = $test->student_tests->first();

        // Is remedial
        switch ($student_test->is_remedial){
            case true:
                $isRemedial = true;
                break;
            default:
                $isRemedial = false;
                break;
        }

        // Result
        $result = [
            'title' => $test->title,
            'description' => $description,
            'main_topic' => $test->learning_contract_information->main_topic,
            'duration' => $test->duration,
            'start_at' => $student_test->start_at,
            'end_at' => $student_test->end_at,
            'score' => $student_test->score,
            'is_remedial' => $isRemedial,
        ];

        return Response::status('success')->result($result);
    }

    /**
     * FORUM TEST UPLOAD
     *
     */
    public function forumTestUpload(Request $request){
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:jpg,jpeg,jfif,heic,png,gif,svg,mp4,m4a,webm,mkv,mov,mp3,wav,flac'
        ]);

        if($validator->fails()){
            return Response::status('failure')->code(401)->result($validator->errors());
        }

        $file       = $request->file('file');
        $fileName   = Str::uuid().'_'.time().'.'.$file->getClientOriginalExtension();
        $pathFile   = 'test/student/';

        // Upload File
        $file->storeAs($pathFile, $fileName, 'gcs');
        $disk = Storage::disk('gcs');
        $path = $disk->url($pathFile.$fileName);

        // Insert data to database
        $newMedia = new Media();
        $newMedia->name = $request->file->getClientOriginalName();
        $newMedia->file = $path;
        $newMedia->extension = $file->getClientOriginalExtension();
        $newMedia->category = 'test';
        $newMedia->size = FileSize::getFileSize($file);
        $newMedia->save();

        $result = [
            'media_id' => $newMedia->id,
            'name' => $newMedia->name,
            'file' => $newMedia->file,
            'size' => $newMedia->size
        ];

        return Response::status('success')->code(201)->result($result);
    }

    /**
     * FORUM TEST SUBMIT
     *
     */
    public function forumTestSubmit(Request $request){
        if(!$request->student_test_id){
            return Response::status('failure')->code(422)->result(["Student Test ID is required!"]);
        }

        $studentTest = StudentTest::find($request->student_test_id);
        $studentTest->end_at = Carbon::now();
        $studentTest->status = "finish";
        $studentTest->save();

        if(!$studentTest){
            return Response::status('failure')->code(422)->result(["Failed to update data!"]);
        }

        return Response::status('success')->code(200)->result([]);
    }

    public function startTest(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $now = Carbon::now();

        $studentTest = StudentTest::where('id',$request->student_test_id)
            ->where('student_id', $student->id)
            ->where('is_allowed', TRUE)
            ->with(['test'=>function($q){
                $q->with('subject','learning_contract_information');
                $q->with('test_question_items.question_item');

            }])
            ->first();
        if(!$studentTest){
            return Response::status('failure')->code(422)->result(["Not allowed to take the test!"]);
        }

        if($studentTest->status == "pending"){
            $studentTest->status = "ongoing";
            $studentTest->student_start_at = $now;
            $studentTest->save();

            $testQuestionItem = TestQuestionItem::where('test_id', $studentTest->test_id)->get();
            $testQuestionItemShuffled =[];
            $i=1;
            $mix_and_match = [];
            foreach($testQuestionItem->shuffle() as $testQuestionItemShuffle) {
                if($testQuestionItemShuffle->question_item->mix_and_match_group){//check mix and match or no
                    if(!isset($mix_and_match[$testQuestionItemShuffle->question_item->mix_and_match_group])){
                        $mix_and_match[$testQuestionItemShuffle->question_item->mix_and_match_group] = $i;
                        $testQuestionItemShuffled[] = [
                            "test_question_item_id"=> $testQuestionItemShuffle->id,
                            "student_test_id"=> $studentTest->id,
                            "order" => $i++,
                        ];
                    }else{
                        $testQuestionItemShuffled[] = [
                            "test_question_item_id"=> $testQuestionItemShuffle->id,
                            "student_test_id"=> $studentTest->id,
                            "order" => $mix_and_match[$testQuestionItemShuffle->question_item->mix_and_match_group],
                        ];
                    }
                }else{
                    $testQuestionItemShuffled[] = [
                        "test_question_item_id"=> $testQuestionItemShuffle->id,
                        "student_test_id"=> $studentTest->id,
                        "order" => $i++,
                    ];
                }
            }

            if($studentTest->student_test_question_items->count() <1){
                $studentTest->student_test_question_items()->createMany($testQuestionItemShuffled);
            }
        }

        $result = [
            'test_title' => $studentTest->test->title,
            'test_duration' => $studentTest->test->duration,
            'student_start_at' => $studentTest->student_start_at,
        ];

        return Response::status('success')->code(200)->result($result);
    }

    public function progressTest(Request $request){
        $user = Auth::user();
        $student = $user->student;

        $StudentTestQuestionItem = StudentTestQuestionItem::where('student_test_id',$request->student_test_id)
            ->where('order',$request->order)
            ->with(['test_question_items'=>function($q){
                $q->with(['question_item'=>function($q){
                    $q->with(['question_item_answers'=>function($q){
                        $q->select('id','question_item_id','answer');
                    }]);
                }]);
                $q->with(['student_test_answers' => function($q){
                    $q->with('media');
                }]);
            }])
            ->with(['student_test' =>function($query){
                $query->with(['test' => function($q){
                    $q->with('learning_contract_information');
                    $q->with('subject');
                }]);
            }])
            ->get();

        // Validate Student Test Question
        if ($StudentTestQuestionItem->isEmpty()){
            return Response::status('failure')->result("Data Empty!");
        }

        // Data for Question Item and Student Question Answer
        $questions =[];
        foreach($StudentTestQuestionItem as $row){
            // Check if student already submit the answer
            if($row->student_test_answers()->exists()){
                $exists = $row->test_question_items->student_test_answers->first();
                $existsAnswer = $exists->answer;

                // Check if answer has any medias
                if ($exists->media()->exists()) {
                    $existsAnswerMedia = [
                        'media_id' => $exists->media->id,
                        'extension' => $exists->media->extension,
                        'file' => $exists->media->file,
                        'name' => $exists->media->name,
                        'size' => $exists->media->size,
                    ];
                }
                else{
                    $existsAnswerMedia = [];
                }
            }
            else{
                $existsAnswer = null;
                $existsAnswerMedia = [];
            }

            // Check if Question Items has any answer or not
            if ($row->test_question_items->question_item->question_item_answers()->exists()){
                $questionItemAnswer = $row->test_question_items->question_item->question_item_answers;
            }
            else{
                $questionItemAnswer = null;
            }

            $questions[]=[
                "test_question_item_id" => $row->test_question_items->id,
                "question" => $row->test_question_items->question_item->question,
                "question_item_answer" => $questionItemAnswer,
                "student_answer" => $existsAnswer,
                "student_answer_media" => $existsAnswerMedia,
            ];
        }
        $result = [
            // "test_title" => $StudentTestQuestionItem->first()->student_test->test->title,
            // "test_duration" => $StudentTestQuestionItem->first()->student_test->test->duration,
            // "test_start_at" => date("H:i d F Y",strtotime($StudentTestQuestionItem->first()->student_test->test->start_at)),
            // "test_end_at" => date("H:i d F Y",strtotime($StudentTestQuestionItem->first()->student_test->test->end_at)),
            // "subject" => $StudentTestQuestionItem->first()->student_test->test->subject->name,
            // "learning_contract_main_topic" => $StudentTestQuestionItem->first()->student_test->test->learning_contract_information->main_topic,
            // "learning_contract_sub_topic" => $StudentTestQuestionItem->first()->student_test->test->learning_contract_information->sub_topic,
            "order" => $request->order,
            "question_type" => $StudentTestQuestionItem->first()->test_question_items->question_item->question_item_type,
            "questions" => $questions,
        ];

        return Response::status('success')->code(200)->result($result);

    }

    public function orderProgressTest(Request $request){
        $StudentTestQuestionItem = StudentTestQuestionItem::select(DB::raw('min(id) as id'),DB::raw('min(student_test_id) as student_test_id'),DB::raw('min(test_question_item_id) as test_question_item_id'),'order')
            ->where('student_test_id',$request->student_test_id)
            ->with(['test_question_items'=>function($q){
                $q->with('student_test_answers.media');
            }])
            ->groupBy('order')
            ->orderBy('order')
            ->get();

        $result=[];
        foreach($StudentTestQuestionItem as $row) {
            $result[]=[
                'order' => $row->order,
                'is_answered'=> $row->test_question_items->student_test_answers->count() < 1?false:true
            ];
        }
        return Response::status('success')->code(200)->result($result);
    }

    public function answerTest(Request $request){
        $studentTestQuestionItem = StudentTestQuestionItem::where('order',$request->order)
            ->where('student_test_id',$request->student_test_id)
            ->get();
        if($studentTestQuestionItem->count()==1){
             $studentTestAnswerCheck = StudentTestAnswer::where('student_test_id',$studentTestQuestionItem->first()->student_test_id)
                ->where('test_question_item_id',$studentTestQuestionItem->first()->test_question_item_id)->first();
            if(!$studentTestAnswerCheck){
                $studentTestAnswer = StudentTestAnswer::create([
                    'student_test_id' => $studentTestQuestionItem->first()->student_test_id,
                    'test_question_item_id' => $studentTestQuestionItem->first()->test_question_item_id,
                    'student_test_question_item_id' => $studentTestQuestionItem->first()->id,
                    'media_id' =>  $request->data['0']['media_id'] ?? null,
                    'answer' =>  $request->data['0']['answer'],
                ]);
            }else{
                $studentTestAnswerCheck->media_id = $request->data['0']['media_id'] ?? null;
                $studentTestAnswerCheck->answer =  $request->data['0']['answer'];
                $studentTestAnswerCheck->save();
            }
        }else{
            foreach($request->data as $row){
                $studentTestAnswerCheck = StudentTestAnswer::where('student_test_id',$request->student_test_id)
                    ->where('test_question_item_id',$row['test_question_item_id'])->first();
                $studentTestQuestionItems = StudentTestQuestionItem::where('test_question_item_id',$row['test_question_item_id'])
                ->where('student_test_id',$request->student_test_id)
                ->first();
                if(!$studentTestAnswerCheck){
                    $studentTestAnswer = StudentTestAnswer::create([
                        'student_test_id' => $request->student_test_id,
                        'test_question_item_id' => $row['test_question_item_id'],
                        'student_test_question_item_id' => $studentTestQuestionItems->id,
                        'media_id' => $row['media_id'] ?? null,
                        'answer' => $row['answer'],
                    ]);
                }else{
                    $studentTestAnswerCheck->media_id = $row['media_id'] ?? null;
                    $studentTestAnswerCheck->answer = $row['answer'];
                    $studentTestAnswerCheck->save();
                }
            }
        }
        return Response::status('success')->code(200)->result([]);

    }

    public function finishTest(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $now = Carbon::now();

        $studentTest = StudentTest::where('id',$request->student_test_id)
            ->where('student_id', $student->id)
            ->with('test.subject')
            ->with(['student'=>function($q){
                $q->with(['student_families'=>function($q){
                    $q->where('relationship_role','parents')->orWhere('relationship_role','vice');
                    $q->with(['family'=>function($q){
                        $q->where('is_alive',true);
                    }]);
                }]);
            }])
            ->first();
            foreach ($studentTest->student->student_families as $row){
                Mail::to($row->family->email)->send(new StudentFinishTestMailer($studentTest));
                }

            if($studentTest->status == "ongoing"){
                $studentTest->status = "finish";
                $studentTest->student_end_at = $now;
                $studentTest->save();
                StudentTestQuestionItem::where('student_test_id',$request->student_test_id)->delete();
            }
        if($studentTest->count()<1){
            return Response::status('failure')->code(422)->result(["Not allowed to end the test!"]);
        }
        return Response::status('success')->code(200)->result([]);
    }


}
