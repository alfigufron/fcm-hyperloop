<?php

namespace App\Http\Controllers;

use App\Imports\QuestionImport;
use App\Models\Classroom;
use App\Models\LearningContract;
use App\Models\Media;
use App\Models\Question;
use App\Models\QuestionItem;
use App\Models\StudentTest;
use App\Models\StudentTestAnswer;
use App\Models\StudentTestQuestionItem;
use App\Models\Subject;
use App\Models\Test;
use App\Models\TestQuestionItem;
use App\Models\TestAttempt;
use App\Utils\Paginate;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TeacherForumTestController extends Controller
{
    // Breadcrumb
    public function header(Request $request){
        $subjectid = $request->subject_id;
        $testid = $request->test_id;

        // Validation
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|numeric',
            'test_id' => 'numeric|nullable'
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
        $subject = Subject::find($subjectid);
        $test = Test::find($testid);

        // Validation
        $material = null;
        if($subject == null){
            return Response::status('failure')->result(["Couldn't find subject"]);
        }
        if($test != null) {
            if ($test->learning_contract_information()->exists()) {
                $material = $test->learning_contract_information->main_topic;
            } else {
                $material = null;
            }
        }

        $result = [
            'first' => $subject->name,
            'second' => $material
        ];

        return Response::status('success')->result($result);
    }

    // Test List
    public function index(Request $request){
        $subjectId = $request->input('subject_id');
        $classroomId = $request->input('classroom_id');
        $search = $request->input('q');

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
        $classroom = Classroom::find($classroomId);
        foreach ($classroom->students as $student){
            $true = $student->pivot->where('is_active', true)->first();
            $year = $true->school_year;
        }

        $tests = Test::with(['test_question_items' => function($query){
            $query->with('question_item');
        }])
            ->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->where('school_year', $year)
            ->orderBy('start_at', 'desc')
            ->whereNull('deleted_at');
        if ($search != null){
            $tests = $tests->where('title', 'ilike', '%'.$search.'%');
        }


        // Pagination
        $page = $tests->paginate('10');
        $total = $page->total();
        $lastpage = $page->lastPage();
        $curpage = $page->currentPage();
        $items = $page->items();

        // Foreach
        $records = [];
        foreach ($items as $item){
            $student = $item->student_tests;
            $finish = count($student->where('status', 'finish'));

            $progress = [
                'done' => $finish,
                'from' => count($student),
            ];

            $multipleItem = 0;
            $essayItem = 0;
            $checkboxItem = 0;
            $mixAndMatchItem = 0;
            $trueOrFalseItem = 0;
            $question_item_list = "";
            foreach ($item->test_question_items as $value){
                $question_item = $value->question_item;
                switch ($question_item->question_item_type){
                    case "multiple":
                        $multipleItem++;
                        break;
                    case "essay":
                        $essayItem++;
                        break;
                    case "mix_and_match":
                        $raw = QuestionItem::query()
                            ->whereHas('test_question_item', function ($q) use ($item){
                                $q->where('test_question_items.test_id', $item->id);
                            })
                            ->select(DB::raw('min(id) as id'), DB::raw('min(question_item_type) as question_item_type'), 'mix_and_match_group')
                            ->where('question_item_type', 'mix_and_match')
                            ->groupBy('mix_and_match_group')->get();

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
            $essayQuestions = $essayItem > 1 ? "questions" : "question";
            $checkboxQuestions = $checkboxItem > 1 ? "questions" : "question";
            $mixAndMatchQuestions = $mixAndMatchItem > 1 ? "questions" : "question";
            $trueOrFalseQuestions = $trueOrFalseItem > 1 ? "questions" : "question";

            if ($multipleItem)
                $question_item_list = "$question_item_list $multipleItem multiple choice $multipleQuestions,";

            if ($checkboxItem)
                $question_item_list =  "$question_item_list $checkboxItem checkbox $checkboxQuestions,";

            if ($mixAndMatchItem)
                $question_item_list =  "$question_item_list $mixAndMatchItem mix and match $mixAndMatchQuestions,";

            if ($trueOrFalseItem)
                $question_item_list =  "$question_item_list $trueOrFalseItem true or false $trueOrFalseQuestions,";

            if ($essayItem)
                $question_item_list =  "$question_item_list $essayItem essay $essayQuestions,";

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

            $dataRecords = [
                'test_id' => $item->id,
                'title' => $item->title,
                'description' => $desc,
                'main_topic' => $item->learning_contract_information->main_topic,
                'start_at' => Carbon::parse($item->start_at)->format(' d F Y - g:i A'),
                'end_at' => Carbon::parse($item->end_at)->format('d F Y - g:i A'),
                'total_point' => $item->total_point,
                'progress' => $progress,
            ];
            array_push($records, $dataRecords);
        }

        $result = [
            'pagination' => [
                'total_page' => $lastpage,
                'total_records' => $total,
                'current_page' => $curpage
            ],
            'records' => $records
        ];

        return Response::status('success')->result($result);
    }

    // Delete a test
    public function delete(Request $request){
        $testId = $request->test_id;

        $test = Test::find($testId);
        $test->deletes();

        if($test == null){
            return Response::status('failure')->result(["Couldn't find test"]);
        }

        return Response::status('success')->result([]);
    }

    // Upload a file for test
    public function upload(Request $request){
        // Part 1 (Upload File)
        // Validate
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        if($validator->fails()){
            return Response::status('failure')->code(401)->result($validator->errors());
        }

        // Request File
        $file       = $request->file('file');

        // Part 2 (Validate and inserting to database)
        // Get Question Main Attributes
        $spreadsheet = IOFactory::load($file);
        $code = $spreadsheet->getSheet(0)->getCellByColumnAndRow(2, 2)->getValue();
        $title = $spreadsheet->getSheet(0)->getCellByColumnAndRow(2, 3)->getValue();
        $subject = ucwords($spreadsheet->getSheet(0)->getCellByColumnAndRow(2, 4)->getValue());
        $grade = $spreadsheet->getSheet(0)->getCellByColumnAndRow(2, 5)->getValue();
        $semester = $spreadsheet->getSheet(0)->getCellByColumnAndRow(2, 6)->getValue();

        // Get Subject
        $forumSubject = Subject::where('name', $subject)->first();

        // Validate Specific Column is exists the value?
        if ($forumSubject == null){
            return Response::status('failure')->code(400)->result(["Couldn't find subject, check if there any typos in excel"]);
        }
        if ($code == null){
            return Response::status('failure')->code(400)->result(["code is required"]);
        }
        if ($title == null){
            return Response::status('failure')->code(400)->result(["title is required"]);
        }
        if ($grade == null){
            return Response::status('failure')->code(400)->result(["grade is required"]);
        }
        if ($semester == null){
            return Response::status('failure')->code(400)->result(["semester is required"]);
        }

        // New Validate Specific grade and semester
        switch ($grade){
            case '1' : case '2' : case '3' : case '4' : case '5' : case '6' :
            $level = 'sd';
            $gradeCorrect = [1,2,3];
            if (!in_array($semester, [1,2,3,4,5,6,7,8,9,10,11,12])){
                return Response::status('failure')->code(400)->result(["Invalid semester value"]);
            }
            break;
            case '7': case '8': case '9':
            $level = 'smp';
            $gradeCorrect = [1,2,3,4];
            if (!in_array($semester, [1,2,3,4,5,6])){
                return Response::status('failure')->code(400)->result(["Invalid semester value"]);
            }
            break;
            case '10': case '11': case '12':
            $level = 'sma';
            $gradeCorrect = [1,2,3,4,5];
            if (!in_array($semester, [1,2,3,4,5,6])){
                return Response::status('failure')->code(400)->result(["Invalid semester value"]);
            }
            break;
            default:
                return Response::status('failure')->code(400)->result(["Invalid grade value"]);
        }

        // Question Items
        $excel = Excel::toCollection(new QuestionImport, $file);
        $first = $excel[0];

        $sumScore = 0;
        // Validate Question Item
        foreach ($first  as $key => $row) {
            if (!$row['score']){
                return Response::status('failure')->result("Some field score are empty");
            }

            if ($row['score'] < 0 || $row['score'] > 100){
                return Response::status('failure')->result("Field score must higher than 0 lower than 100");
            }

            $sumScore += $row['score'];


            $type = Str::slug($row['type'], '_');
            switch ($type){
                case "multiple":
                    if ($row['answer_media'] == 'no') {
                        // Answer must be filled depending on grade
                        switch ($level){
                            case 'sd':
                                $validate = $row['answer_1'] != null && $row['answer_2'] != null && $row['answer_3'] != null;
                                break;
                            case 'smp':
                                $validate = $row['answer_1'] != null && $row['answer_2'] != null && $row['answer_3'] != null && $row['answer_4'] != null;
                                break;
                            case 'sma':
                                $validate = $row['answer_1'] != null && $row['answer_2'] != null && $row['answer_3'] != null && $row['answer_4'] != null && $row['answer_5'] != null;
                                break;
                            default:
                                break;
                        }
                        if ($validate == false) {
                            return Response::status('failure')->code(400)->result(["Some field answer is missing or 'answer_media' value is wrong"]);
                        }
                    }

                    // Determine if question has media or not
                    if ($row['question_media'] == 'no'){
                        $questionValidate = $row['question'] != null;
                        if ($questionValidate == false){
                            return Response::status('failure')->code(400)->result(["Some field question is missing or 'question_media' value is wrong"]);
                        }
                    }

                    // Correct must be present
                    if(!in_array($row['correct'], $gradeCorrect)){
                        return Response::status('failure')->code(400)->result(["Some field correct is incorrect value, check if there any typos"]);
                    }
                    break;

                case "true_or_false":
                    // Creating true or false string not boolean
                    $answer1 = $row['answer_1'] == true ? 'true' : 'false';
                    $answer2 = $row['answer_2'] == true ? 'true' : 'false';

                    // Validate 2 answer can only be present
                    $validate = $answer1 != null && $answer2 != null;
                    if ($validate == false) {
                        return Response::status('failure')->code(400)->result(["some field 'answer_1' or 'answer_2' in true or false question is missing or 'answer_media' value is wrong"]);
                    }

                    // Validate correct must be present whether it 1 or 2
                    if (!in_array($row['correct'], [1,2])){
                        return Response::status('failure')->code(400)->result(["Some field correct is incorrect value, check if there any typos"]);
                    }
                    break;

                case 'checkbox':
                    // Determine if answer has media
                    if ($row['answer_media'] == 'no') {
                        $validate = $row['answer_1'] != null && $row['answer_2'] != null && $row['answer_3'] != null && $row['answer_4'] != null && $row['answer_5'] != null;
                        if ($validate == false) {
                            return Response::status('failure')->code(400)->result(["Some field answer is missing or 'answer_media' value is wrong"]);
                        }
                    }

                    // Checking correct can't be can be lower than 1 nor higher than 5
                    if (!in_array($row['correct'], [1,2,3,4,5])){
                        return Response::status('failure')->code(400)->result(["Some field correct is incorrect value, check if there any typos"]);
                    }

                    // Determine if question has media
                    if ($row['question_media'] == 'no'){
                        $questionValidate = $row['question'] != null;
                        if ($questionValidate == false){
                            return Response::status('failure')->code(400)->result(["Some field question is missing or 'question_media' value is wrong"]);
                        }
                    }
                    break;

                case 'mix_and_match':
                    // Determine if question has media
                    if ($row['question_media'] == 'no'){
                        $questionValidate = $row['question'] != null;
                        if ($questionValidate == false){
                            return Response::status('failure')->code(400)->result(["Some field question is missing or 'question_media' value is wrong"]);
                        }
                    }

                    // Determine if answer has media
                    if ($row['answer_media'] == 'no'){
                        $validateAnswerMixMatch = $row['answer_1'] !== null;
                        if ($validateAnswerMixMatch == false){
                            return Response::status('failure')->code(400)->result("field 'answer_1' in 'mix and match' type must be filled");
                        }
                    }

                    // Correct value must be equal to 1
                    if ($row['correct'] !== 1){
                        return Response::status('failure')->code(400)->result("the correct answer value must be 1");
                    }
                    break;

                case 'essay':
                    // Determine if question has media
                    if ($row['question_media'] == 'no'){
                        $questionValidate = $row['question'] != null;
                        if ($questionValidate == false){
                            return Response::status('failure')->code(400)->result(["Some field question is missing or 'question_media' value is wrong"]);
                        }
                    }
                    break;
                default :
                    break;
            }
        }

        // Validate of sum score
        switch ($sumScore){
            case $sumScore > 100:
                return Response::status('failure')->result("Score can't be higher than 100");
            case $sumScore < 100:
                return Response::status('failure')->result("Score can't be lower than 100");
            default:
                break;
        }

        // Create new Question
        $newQuestion = new Question();
        $newQuestion->code = $code;
        $newQuestion->title = $title;
        $newQuestion->subject_id = $forumSubject->id;
        $newQuestion->grade = $grade;
        $newQuestion->semester = $semester;
        $newQuestion->save();

        // Insert Relation Question is it schools or dormitories
        $user = Auth::user();
        $teacher = $user->teacher;
        if ($teacher->schools()->exists()){
            $SchoolOrDorm = $teacher->schools()->first();
            $newQuestion->schools()->attach($SchoolOrDorm->id);
        }
        elseif ($teacher->dormitories()->exists()){
            $SchoolOrDorm = $teacher->dormitories()->first();
            $newQuestion->dormitories()->attach($SchoolOrDorm->id);
        }

        // To avoid duplicate data mix and match group column
        $mixAndMatchGroup = QuestionItem::select(DB::raw('min(id) as id'), 'mix_and_match_group')
            ->whereNotNull('mix_and_match_group')->groupBy('mix_and_match_group')->orderBy('mix_and_match_group', 'desc')
            ->first()->mix_and_match_group;
        foreach ($first  as $key => $row) {

            $type = Str::slug($row['type'], '_');

            $newQuestionItem = new QuestionItem();
            $newQuestionItem->question_id = $newQuestion->id;
            $newQuestionItem->question = $row['question'];
            $newQuestionItem->level = $row['level'];
            $newQuestionItem->question_item_type = $type;
            $newQuestionItem->score = $row['score'];
            $newQuestionItem->push();
            if ($row['discuss'] != null){
                $newQuestionItem->question_item_discussses()->create([
                    'discuss' => $row['discuss']
                ]);
            }

            // Insert Question Item Answers
            switch ($type) {
                case 'multiple':
                    // The answer depending on grade
                    switch ($level) {
                        case 'sd':
                            $correct1 = $row['correct'] === 1;
                            $correct2 = $row['correct'] === 2;
                            $correct3 = $row['correct'] === 3;
                            $answers = [
                                [
                                    'answer' => $row['answer_1'],
                                    'is_correct' => $correct1
                                ],
                                [
                                    'answer' => $row['answer_2'],
                                    'is_correct' => $correct2
                                ],
                                [
                                    'answer' => $row['answer_3'],
                                    'is_correct' => $correct3
                                ]
                            ];
                            break;
                        case 'smp':
                            $correct1 = $row['correct'] === 1;
                            $correct2 = $row['correct'] === 2;
                            $correct3 = $row['correct'] === 3;
                            $correct4 = $row['correct'] === 4;
                            $answers = [
                                [
                                    'answer' => $row['answer_1'],
                                    'is_correct' => $correct1
                                ],
                                [
                                    'answer' => $row['answer_2'],
                                    'is_correct' => $correct2
                                ],
                                [
                                    'answer' => $row['answer_3'],
                                    'is_correct' => $correct3
                                ],
                                [
                                    'answer' => $row['answer_4'],
                                    'is_correct' => $correct4
                                ]
                            ];
                            break;
                        case 'sma':
                            $correct1 = $row['correct'] === 1;
                            $correct2 = $row['correct'] === 2;
                            $correct3 = $row['correct'] === 3;
                            $correct4 = $row['correct'] === 4;
                            $correct5 = $row['correct'] === 5;
                            $answers = [
                                [
                                    'answer' => $row['answer_1'],
                                    'is_correct' => $correct1
                                ],
                                [
                                    'answer' => $row['answer_2'],
                                    'is_correct' => $correct2
                                ],
                                [
                                    'answer' => $row['answer_3'],
                                    'is_correct' => $correct3
                                ],
                                [
                                    'answer' => $row['answer_4'],
                                    'is_correct' => $correct4
                                ],
                                [
                                    'answer' => $row['answer_5'],
                                    'is_correct' => $correct5
                                ]
                            ];
                            break;
                        default:
                            break;
                    }
                    $newQuestionItem->question_item_answers()->createMany($answers);
                    break;

                case "true_or_false":
                    $answer1 = $row['answer_1'] === true ? 'true' : 'false';
                    $answer2 = $row['answer_2'] === true ? 'true' : 'false';

                    $correct1 = $row['correct'] === 1;
                    $correct2 = $row['correct'] === 2;
                    $answers = [
                        [
                            'answer' => $answer1,
                            'is_correct' => $correct1
                        ],
                        [
                            'answer' => $answer2,
                            'is_correct' => $correct2
                        ]
                    ];
                    $newQuestionItem->question_item_answers()->createMany($answers);
                    break;
                case "checkbox":
                    $corrects = $row['correct'];

                    // Exploding the coma separator
                    $explodedCorrects = explode(',', $corrects);
                    foreach ($explodedCorrects as $correct){
                        $rawCorrect1[] = $correct == 1;
                        $rawCorrect2[] = $correct == 2;
                        $rawCorrect3[] = $correct == 3;
                        $rawCorrect4[] = $correct == 4;
                        $rawCorrect5[] = $correct == 5;
                    }

                    // Checking if answer(n) has correct value
                    $intCorrect1 = count(array_filter($rawCorrect1));
                    $intCorrect2 = count(array_filter($rawCorrect2));
                    $intCorrect3 = count(array_filter($rawCorrect3));
                    $intCorrect4 = count(array_filter($rawCorrect4));
                    $intCorrect5 = count(array_filter($rawCorrect5));

                    // Resulting answer(n) is it true or false
                    $correct1 = $intCorrect1 > 0;
                    $correct2 = $intCorrect2 > 0;
                    $correct3 = $intCorrect3 > 0;
                    $correct4 = $intCorrect4 > 0;
                    $correct5 = $intCorrect5 > 0;

                    $answers = [
                        [
                            'answer' => $row['answer_1'],
                            'is_correct' => $correct1
                        ],
                        [
                            'answer' => $row['answer_2'],
                            'is_correct' => $correct2
                        ],
                        [
                            'answer' => $row['answer_3'],
                            'is_correct' => $correct3
                        ],
                        [
                            'answer' => $row['answer_4'],
                            'is_correct' => $correct4
                        ],
                        [
                            'answer' => $row['answer_5'],
                            'is_correct' => $correct5
                        ]
                    ];
                    $newQuestionItem->question_item_answers()->createMany($answers);
                    break;
                case "mix_and_match":
                    $correct = $row['correct'] == 1;

                    $answers = [
                        [
                            'answer' => $row['answer_1'],
                            'is_correct' => $correct
                        ]
                    ];
                    $newQuestionItem->mix_and_match_group = $mixAndMatchGroup + $row['mix_and_match_group'];
                    $newQuestionItem->save();
                    $newQuestionItem->question_item_answers()->createMany($answers);
                    break;
                default:
                    break;
            }
            $newQuestionItem->push();
        }

//        // Media Metadata
//        $fileName =  time()."-".$request->file->getClientOriginalName();
//        $pathFile = 'question-bank/file-format';
//
//        $file->storeAs($pathFile, $fileName, 'gcs');
//        $disk = Storage::disk('gcs');
//        $path = $disk->url($pathFile.$fileName);
//
//        // Inserting Media
//        $newMedia = new Media();
//        $newMedia->name = $request->file->getClientOriginalName();;
//        $newMedia->file = $path;
//        $newMedia->extenstion = $file->getClientOriginalExtension();
//        $newMedia->type = 'general';
//        $newMedia->size = FileSize::getFileSize($file);
//        $newMedia->save();

        return Response::status('success')->result(['question_id' => $newQuestion->id, 'question_code' => $newQuestion->code]);
    }

    public function learningMaterial(Request $request){
        $user = Auth::user();
        $teacherId = $user->teacher->id;
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

        $main_topic = [];
        foreach ($lc as $item){
            foreach ($item->learning_contract_informations as $lci){
                $main_topic[] = [
                    'learning_contract_information_id' => $lci->id,
                    'main_topic' => $lci->main_topic
                ];
            }
        }

        $result = $main_topic;

        return Response::status('success')->result($result);
    }

    // Create a test
    public function create(Request $request){
        // Prerequisites
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');
        $learningContractInformationId = $request->input('learning_contract_information_id');
        $questionId = $request->input('question_id');
        $startAt = $request->input('start_at')['date']." ".$request->input('start_at')['time'];
        $endAt = $request->input('end_at')['date']." ".$request->input('end_at')['time'];

        // Validate
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
            'question_id' => 'required|numeric',
            'learning_contract_information_id' => 'required|numeric'
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

        //Check Question
        $question = Question::with(['question_items' => function($query){
            $query->with('question_item_answers');
            $query->with('question_item_discussses');
            }])
            ->where('id', $questionId)
            ->first();

        if ($question == null){
            return Response::status('failure')->code(204)->result(["Couldn't find Question"]);
        }

        // School Year
        $classroom = Classroom::with(['students' => function($query){
                $query->wherePivot('is_active', true);
            }])->where('id', $classroomId)->first();
        $year = $classroom->students->first()->pivot->school_year;

        // Create Test
        $newTest = new Test();
        $newTest->classroom_id = $classroomId;
        $newTest->subject_id = $subjectId;
        $newTest->learning_contract_information_id = $learningContractInformationId;
        $newTest->title = $request->input('title');
        $newTest->test_type = $request->input('test_type');
        $newTest->start_at = $startAt;
        $newTest->end_at = $endAt;
        $newTest->duration = $request->input('duration');
        $newTest->total_point = $request->input('total_point');
        $newTest->grade = $classroom->grade;
        $newTest->semester = $newTest->learning_contract_information->learning_contract->semester;
        $newTest->school_year = $year;
        $newTest->save();

        //create test question items
        $dataTestQuestionItem = [];
        foreach ($question->question_items as $item){
            $dataTestQuestionItem[] = [
                'test_id' => $newTest->id,
                'question_item_id' => $item->id,
            ];
        }
        $newTest->test_question_items()->createMany($dataTestQuestionItem);

        // Insert Student
        $studentTest = [];
        foreach ($classroom->students as $student){
            $dataStudentTest = [
                'student_id' => $student->id,
                'test_id' => $newTest->id,
                'student_start_at' => null,
                'student_end_at' => null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'pending',
                'is_allowed' => true,
                'score' => null,
                'is_remedial' => false,
            ];
            array_push($studentTest, $dataStudentTest);
        }
        $newTest->student_tests()->createMany($studentTest);

        return Response::status('success')->code(201)->result(null);
    }

    // Test Detail
    public function detail(Request $request){
        // Prerequisites
        $testId = $request->test_id;

        // Validate
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

        $test = Test::with(['test_question_items' => function($query){
                $query->orderBy('question_item_id');
                $query->with(['question_item' => function($q){
                    $q->with('question_item_answers');
                }]);
            }])
            ->where('id', $testId)
            ->first();

        if ($test == null){
            return Response::status('failure')->result(["Couldn't find test"]);
        }

        $question = [];
        foreach ($test->test_question_items as $test_question_item) {
            $question_item = $test_question_item->question_item;
            switch ($question_item->question_item_type){
                case 'multiple':case 'checkbox':case 'true_or_false':
                    $answers = [];
                    foreach ($question_item->question_item_answers as $answer) {
                        $dataAnswer = [
                            'answer' => $answer->answer,
                        ];
                        array_push($answers, $dataAnswer);
                    }

                    $question[] = [
                        'type' => $question_item->question_item_type,
                        'questions' => [
                            'test_question_id' => $test_question_item->id,
                            'question' => $question_item->question,
                            'score' => $question_item->score,
                        ],
                        'answers' => $answers,
                    ];
                    break;
                case 'essay':
                    $question[] = [
                        'type' => 'essay',
                        'questions' => [
                            'test_question_id' => $test_question_item->id,
                            'question' => $question_item->question,
                            'score' => $test_question_item->score,
                        ],
                        'answers' => null,
                    ];
                    break;
                default:
                    break;
            }
        }

        $rawMixMatch = QuestionItem::select(DB::raw('min(id) as id'), DB::raw('min(question_item_type) as question_item_type'), 'mix_and_match_group')
            ->with('question_item_answers')
            ->whereHas('test_question_item', function ($query) use ($testId) {
                $query->where('test_question_items.test_id', $testId);
            })
            ->where('question_item_type', 'mix_and_match')
            ->groupby('mix_and_match_group')
            ->get();


        foreach ($rawMixMatch as $item){
            $valMixMatch = $item->mix_and_match_group;
            $mixMatch = QuestionItem::with('question_item_answers')
                ->with('test_question_item')
                ->whereHas('test_question_item', function ($query) use($testId){
                    $query->where('test_id', $testId);
                })
                ->where('question_item_type', 'mix_and_match')
                ->where('mix_and_match_group', $valMixMatch)
                ->orderBy('id')->get();

            $rawQuestion = [];
            $rawAnswer = [];
            foreach ($mixMatch as $nestedItem){
                foreach ($nestedItem->question_item_answers as $answer){
                    $dataRawAnswer = [
                        'answer' => $answer->answer
                    ];
                    array_push($rawAnswer, $dataRawAnswer);
                }

                $dataRawQuestion = [
                    'test_question_id' => $nestedItem->test_question_item->first()->id,
                    'question' => $nestedItem->question,
                    'score' => $nestedItem->score
                ];
                array_push($rawQuestion, $dataRawQuestion);
            }

            $rawFull = [
                'type' => 'mix_and_match',
                'questions' => $rawQuestion,
                'answers' => $rawAnswer
            ];
            array_push($question, $rawFull);

        }

        return Response::status('success')->result($question);
    }

    // Edit score question in a test / Input Score Question
    public function edit(Request $request){
        $testId = $request->input('test_id');
        $data = $request->get('data');

        // Validate request param
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

        // Query
        $test = Test::find($testId);

        // Validate Test
        if ($test == null){
            return Response::status('failure')->code(204)->result(["Couldn't Find Test"]);
        }

        foreach ($data as $d => $value){
            $testQuestionItemId = Arr::get($value, 'test_question_item');
            $testQuestionItemScore = Arr::get($value, 'score');

            $testQuestionItem = $test->test_question_items->find($testQuestionItemId);
            $testQuestionItem->score = $testQuestionItemScore;
            $testQuestionItem->push();
        }

        return Response::status('success')->result(null);
    }

    // Test info
    public function progressInfo(Request $request){
        $testId = $request->test_id;

        // Validate request param
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

        $test = Test::find($testId);

        // Validate test record
        if($test == null){
            return Response::status('failure')->code(204)->result(["Couldn't find test"]);
        }

        $result = [
            'name' => $test->title,
            'material' => $test->learning_contract_information->main_topic,
            'start_at' => Carbon::parse($test->start_at)->format('g:i A - d F Y'),
            'end_at' => Carbon::parse($test->end_at)->format('g:i A - d F Y'),
            'total_point' => $test->total_point
        ];

        return Response::status('success')->result($result);
    }

    // Test Progress Detail
    public function progressDetail(Request $request){
        $testId = $request->test_id;

        // Validate request param
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

        //Query
        $test = Test::where('id', $testId)
            ->with(['student_tests' => function($query){
                $query->select(DB::raw('max(id) as id'), DB::raw('min(test_id) as test_id'), 'student_id',
                                DB::raw('max(status) as status'), DB::raw('bool_or(is_remedial) as is_remedial'),
                                DB::raw('max(score) as score'), DB::raw('max(created_at) as created_at'));
                $query->orderBy('created_at');
                $query->groupBy('student_id');
                $query->with('student_test_answers');
            }])
            ->first();

        if (!$test){
            return Response::status('failure')->result("couldn't find any matching data");
        }

        // Students
        $students = [];
        foreach($test->student_tests as $item){
            // Student Grade
            $student = $item->student;
            $classroom = $student->classrooms()->wherePivot('is_active', true)->where('classroom_type', 'regular')->first();

            // Student Progress status
            switch ($item->status){
                case 'pending':
                    $collect = false;
                    $start = false;
                    break;
                case 'ongoing':
                    $collect = false;
                    $start = true;
                    break;
                case 'finish':
                    $collect = true;
                    $start = true;
                    break;
                default:
                    $collect = null;
                    $start = null;
                    break;
            }

            // Student Remedial status
            switch ($item->is_remedial){
                case false:
                case null:
                    $remedial = false;
                    break;
                case true:
                    $remedial = true;
                    break;
            }

            $dataStudents = [
                'student_test_id' => $item->id,
                'student_id' => $item->student_id,
                'name' => $student->name,
                'profile' => $student->profile_picture,
                'grade' => $classroom->grade,
                'nis' => $student->nis,
                'is_submitted' => $collect,
                'is_started' => $start,
                'is_remedial' => $remedial,
                'score' => $item->score,
                'progress' => [
                    'done' => count($item->student_test_answers),
                    'from' => count($test->test_question_items),
                ],
            ];

            array_push($students, $dataStudents);
        }

        $finalResult = collect($students)->sortByDesc('name')->toArray();

        $page = Paginate::paginator($finalResult);
        $total = $page->total();
        $lastPage = $page->lastPage();
        $curPage = $page->currentPage();
        $items = $page->items();

        $result = [
            'paginate' => [
                'total_page' => $lastPage,
                'total_record' => $total,
                'current_page' => $curPage
            ],
            'records' => array_values($items)
        ];

        return Response::status('success')->result($result);
    }

    // Test Progress Student
    public function progresStudent(Request $request){
        $testId = $request->test_id;
        $studentTestId = $request->input('student_test_id');

        // Validate request param
        $validator = Validator::make($request->all(), [
            'test_id' => 'required|numeric',
            'student_test_id' => 'required|numeric'
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

        $test = Test::where('id', $testId)
            ->with(['student_tests' => function($query) use($studentTestId){
                $query->with(['student' => function($q){
                    $q->with('student_detail');
                }]);
                $query->where('id', $studentTestId);
                $query->with('student_test_answers.media');
            }])
            ->first();

        if (!$test){
            return Response::status('failure')->code(204)->result(["Couldn't find test"]);
        }
        if(!$test->student_tests->first()){
            return Response::status('failure')->result("Couldn't find student test!");
        }

        // Minimum Competency
        if($test->classroom->schools()->exists()){
            $school = $test->classroom->schools()->first();
            switch ($school->name){
                case 'SD IIBS':
                case 'SMP IIBS':
                    $passingGrade = $school->subjects()->wherePivot('subject_id', $test->subject_id)->first()->minimum_competency;
                    break;
                default:
                    $passingGrade = $school->school_minimum_competency->value;
                    break;
            }
        }
        elseif($test->classroom->dormitories()->exists()){
            $dorm = $test->classroom->dormitories()->first();
            $school = $test->classroom->dormitories()->first()->schools;
            switch ($school->name){
                case 'SD IIBS':
                case 'SMP IIBS':
                    $passingGrade = $dorm->subjects()->wherePivot('subject_id', $test->subject_id)->first()->minimum_competency;
                    break;
                default:
                    $passingGrade = $dorm->dormitory_minimum_competency->value;
                    break;
            }
        }
        else{
            $passingGrade = null;
        }

        /**
         * GET QUESTION ITEMS
         */
        $records = [];
        // Query get except mix and match
        $questionIds = TestQuestionItem::query()
            ->where('test_id', $testId)
            ->whereHas('question_item', function ($query){
                $query->whereIn('question_items.question_item_type', ['multiple', 'essay', 'true_or_false' ,'checkbox']);
            })
            ->get();

        $curStudentTest = $test->student_tests->first();
        if ($curStudentTest->status != 'finish'){
            return Response::status('failure')->result("Not Allowed");
        }

        $raw = TestQuestionItem::query()
            ->whereIn('id', $questionIds->pluck('id'))
            ->with(['student_test_answers' => function($query) use ($curStudentTest) {
                $query->where('student_test_id', $curStudentTest->id);
            }])
            ->with(['question_item' => function($q){
                $q->with('media');
                $q->with(['question_item_answers' => function($nested){
                    $nested->with('media');
                }]);
            }])
            ->get();

//        $raw = StudentTestQuestionItem::query()
//            ->whereIn('test_question_item_id', $questionIds->pluck('id'))
//            ->where('student_test_id', $curStudentTest->id)
//            ->orderBy('test_question_item_id')
//            ->with('student_test_answers')
//            ->with(['test_question_items' => function($query){
//                $query->with(['question_item' => function($q){
//                    $q->with('media');
//                    $q->with(['question_item_answers' => function($nested){
//                        $nested->with('media');
//                    }]);
//                }]);
//            }])
//            ->get();

        $rawTotalScore = 0;
        foreach ($raw as $item){
            $question = $item->question_item;
            // For student data (answer and media)
            if ($item->student_test_answers->isNotEmpty()) {
                $studentAnswers = $item->student_test_answers->first();

                $studentAnswer = $studentAnswers->answer;
                // Student Attachment
                if ($studentAnswers->media_id != null) {
                    $studentMedia = [
                        'media_id' => $studentAnswers->media->id,
                        'extension' => $studentAnswers->media->extension,
                        'file' => $studentAnswers->media->file,
                        'name' => $studentAnswers->media->name,
                        'size' => $studentAnswers->media->size,
                    ];
                } else {
                    $studentMedia = [];
                }

                // Student Correct Answer
                $explodeAnswer = explode(',', $studentAnswer);
                $correctAnswers = $question->question_item_answers->where('is_correct', true)->sortBy('id');
                $totalCorrect = count($correctAnswers);
                // Checking score for all type except essay
                if ($studentAnswers->score === null) {
                    // Check jawaban siswa apakah sama totalnya dengan question_item_answers
                    if (count($explodeAnswer) == count($correctAnswers)) {
                        $i = 0;
                        foreach ($explodeAnswer as $value) {
                            // Check jawaban siswa apakah sama dengan question_item_answersnya
                            if (in_array($value, $correctAnswers->pluck('id')->toArray())) {
                                $i++;
                            }
                        }

                        // Check apakah jawaban siswa benarnya sama dengan qustion_item_answers
                        if ($i == $totalCorrect) {
                            $score = $question->score;
                        } else {
                            $score = 0;
                        }

                    }

                    else {
                        $score = 0;
                    }
                }

                // score untuk jawaban essay
                else{
                    $score = $studentAnswers->score;
                }

            }

            // Jika siswa tidak menjawab soal
            else{
                $studentAnswer = null;
                $studentMedia = [];
                $score = 0;
            }

            // Question Attachment
            $questionMedia = [];
            foreach ($question->media as $media){
                $dataMedia = [
                    'media_id' => $media->id,
                    'extension' => $media->extension,
                    'file' => $media->file,
                    'name' => $media->name,
                    'size' => $media->size
                ];
                array_push($questionMedia, $dataMedia);
            }

            // Question Answer
            $questionAnswer = [];
            foreach ($question->question_item_answers as $value){
                // Answer Media
                $questionAnswerMedia = [];
                foreach ($value->media as $media){
                    $dataQuestionAnswerMedia = [
                        'media_id' => $media->id,
                        'extension' => $media->extension,
                        'file' => $media->file,
                        'name' => $media->name,
                        'size' => $media->size
                    ];
                    array_push($questionAnswerMedia, $dataQuestionAnswerMedia);
                }

                $dataAnswer = [
                    'id' => $value->id,
                    'answer' => $value->answer,
                    'media' => $questionAnswerMedia
                ];
                array_push($questionAnswer, $dataAnswer);
            }
            if ($questionAnswer == []){
                $questionAnswer = null;
            }

            $data = [
                'type' => $question->question_item_type,
                'data' => [
                    'question_item_id' => $question->id,
                    'test_question_item_id' => $item->id,
                    'question' => $question->question,
                    'question_score' => $question->score,
                    'question_answers' => $questionAnswer,
                    'question_media' => $questionMedia,
                    'student_answer' => $studentAnswer,
                    'student_attachment' => $studentMedia,
                    'score' => $score
                ]
            ];

            $rawTotalScore += $score;

            array_push($records, $data);
        }

        // Get Mix and Match Question
        $rawMixMatch = QuestionItem::select(DB::raw('min(id) as id'), DB::raw('min(question_item_type) as question_item_type'), 'mix_and_match_group')
            ->whereHas('test_question_item', function ($query) use ($testId) {
                $query->where('test_id', $testId);
            })
            ->with(['test_question_item' => function($query) use($testId){
                $query->where('test_id', $testId);
            }])
            ->where('question_item_type', 'mix_and_match')
            ->groupby('mix_and_match_group')
            ->get();


        foreach ($rawMixMatch as $item){
            $valMixMatch = $item->mix_and_match_group;
            $mixMatch = QuestionItem::query()
                ->with('media')
                ->with(['question_item_answers' => function($q){
                    $q->with('media');
                }])
                ->with(['test_question_item' => function($query) use($testId){
                    $query->where('test_id', $testId);
                }])
                ->whereHas('test_question_item', function ($query) use($testId){
                    $query->where('test_id', $testId);
                })
                ->where('question_item_type', 'mix_and_match')
                ->where('mix_and_match_group', $valMixMatch)
                ->orderBy('id')->get();

            $rawQuestion = [];
            foreach ($mixMatch as $nestedItem){
                // Student Answers Raw
                $rawStudent = StudentTestAnswer::query()
                    ->orderBy('test_question_item_id')
                    ->where('student_test_id', $studentTestId)
                    ->where('test_question_item_id', $nestedItem->test_question_item->first()->id)
                    ->first();


                // Determine if student answered the question
                if ($rawStudent != null){
                    // Student Answer Media
                    if ($rawStudent->media_id != null){
                        $rawStudentMedia = [
                            'media_id' => $rawStudent->media->id,
                            'extension' => $rawStudent->media->extension,
                            'file' => $rawStudent->media->file,
                            'name' => $rawStudent->media->name,
                            'size' => $rawStudent->media->size,
                        ];
                    }
                    else{
                        $rawStudentMedia = [];
                    }

                    // Student Answer Correct
                    if($rawStudent->answer == $nestedItem->question_item_answers->where('is_correct', true)->first()->id){
                        $score = $nestedItem->score;
                    }
                    else{
                        $score = 0;
                    }

                    $rawStudentAnswer = $rawStudent->answer;
                }
                else{
                    $rawStudentMedia = [];
                    $score = 0;
                    $rawStudentAnswer = null;
                }

                // Question Media
                $questionMedia = [];
                foreach ($nestedItem->media as $media){
                    $dataQuestionMedia = [
                        'media_id' => $media->id,
                        'file' => $media->file,
                        'extension' => $media->extension,
                        'name' => $media->name,
                        'size' => $media->size,
                    ];
                    array_push($questionMedia, $dataQuestionMedia);
                }

                // Question Answer
                $rawAnswer = [];
                foreach ($nestedItem->question_item_answers as $answer){
                    // Media
                    $answerMedia = [];
                    foreach ($answer->media as $media){
                        $dataAnswerMedia = [
                            'media_id' => $media->id,
                            'file' => $media->file,
                            'extension' => $media->extension,
                            'name' => $media->name,
                            'size' => $media->size,
                        ];
                        array_push($answerMedia, $dataAnswerMedia);
                    }

                    // Answer
                    $dataRawAnswer = [
                        'answer' => $answer->answer,
                        'media' => $answerMedia
                    ];
                    array_push($rawAnswer, $dataRawAnswer);
                }

                $dataRawQuestion = [
                    'question_item_id' => $nestedItem->id,
                    'test_question_item_id' => $nestedItem->test_question_item->first()->id,
                    'question' => $nestedItem->question,
                    'question_score' => $nestedItem->score,
                    'question_answers' => $rawAnswer,
                    'question_media' => $questionMedia,
                    'student_answer' => $rawStudentAnswer,
                    'student_attachment' => $rawStudentMedia,
                    'score' => $score
                ];
                array_push($rawQuestion, $dataRawQuestion);
                $rawTotalScore += $score;
            }

            $rawFull = [
                'type' => 'mix_and_match',
                'data' => $rawQuestion,
            ];
            array_push($records, $rawFull);
        }
        /**
         * END OF QUESTION
         */


        // Student final score
        $studentFinalScore = $test->student_tests->first()->score;
        $isChecked = $studentFinalScore != null;

        $result = [
            'student_name' => $test->student_tests->first()->student->name,
            'student_profile' => $test->student_tests->first()->student->student_detail->profile,
            'student_final_score' => $test->student_tests->first()->score,
            'student_has_checked' => $isChecked,
            'title' => $test->title,
            'material' => $test->learning_contract_information->main_topic,
            'test_total_score' => $test->total_point,
            'student_checked_score' => $rawTotalScore,
            'minimum_competency' => $passingGrade,
            'questions' => $records
        ];

        return Response::status('success')->result($result);
    }

    // Input Score Student
    public function inputScoreStudent(Request $request){
        $testId = $request->get('test_id');
        $studentTestId = $request->input('student_test_id');
        $data = $request->get('data');

        // Validate request param
        $validator = Validator::make($request->all(), [
            'test_id' => 'required|numeric',
            'student_test_id' => 'required|numeric'
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

        // Query Student Test
        $studentTest = StudentTest::query()
            ->where('id', $studentTestId)
            ->with('test')
            ->with('student_test_answers')
            ->first();

        // Validate Student Test
        if ($studentTest == null){
            return Response::status('failure')->result("Couldn't find matching data");
        }

        // Get School or Minimum Competency
        $test = $studentTest->test;
        if($test->classroom->schools()->exists()){
            $school = $test->classroom->schools()->first();
            switch ($school->name){
                case 'SD IIBS':
                case 'SMP IIBS':
                    $passingGrade = $school->subjects()->wherePivot('subject_id', $test->subject_id)->first()->minimum_competency;
                    break;
                default:
                    $passingGrade = $school->school_minimum_competency->value;
                    break;
            }
        }
        elseif($test->classroom->dormitories()->exists()){
            $dorm = $test->classroom->dormitories()->first();
            $school = $test->classroom->dormitories()->first()->schools;
            switch ($school->name){
                case 'SD IIBS':
                case 'SMP IIBS':
                    $passingGrade = $dorm->subjects()->wherePivot('subject_id', $test->subject_id)->first()->minimum_competency;
                    break;
                default:
                    $passingGrade = $dorm->dormitory_minimum_competency->value;
                    break;
            }
        }

        // Scoring
        $totalScore = 0;
        foreach ($data as $value){
            $testQuestionItemId =  Arr::get($value, 'test_question_item_id');
            $studentTestAnswerScore = Arr::get($value, 'score');

            $studentTestAnswers = $studentTest->student_test_answers->where('test_question_item_id', $testQuestionItemId)->first();
            $studentTestAnswers->score = $studentTestAnswerScore;
            $studentTestAnswers->push();
            $totalScore += $studentTestAnswerScore;
        }

        // Update Student Test Score
        $checkedScore = $request->input('total_score');
        $finalStudentScore = $checkedScore + $totalScore;
        $studentTest->score = $finalStudentScore;
        $studentTest->save();

        // Student Test Attempt
//        $newTestAttempt = new TestAttempt();
//        $newTestAttempt->test_id = $testId;
//        $newTestAttempt->student_id = $studentTest->student_id;
//        $newTestAttempt->score = $totalScore;
//        $newTestAttempt->status = $totalScore < $passingGrade ? "remedial" : 'passed';
//        $newTestAttempt->save();

        return Response::status('success')->result();
    }

    //Form input Test
    public function inputTest(){
        $user = Auth::user();
        $teacher = $user->teacher;
        $data['classroom'] = Classroom::orderBy('name')->with('students')->get();
        $data['subject'] = Subject::all();
        $data['learningContract'] = LearningContract::all();
        $data['question'] = Question::with('subjects')->get();


        return Response::status('success')->result($data);
    }

    public function testScoring(Request $request){

        $testId = $request->test_id;
        $studentId = $request->student_id;

        // Validation
        $validator = Validator::make($request->all(), [
            'test_id' => 'required|numeric',
            'student_id' => 'numeric'
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

        $studentTest = StudentTest::where('test_id', $testId)
            ->where('student_id', $studentId)
            ->with('student.student_detail')
            ->with('test.subject','test.learning_contract_information')
            ->with(['student_test_answers'=>function($q){
                $q->with('test_questions.question_item.question_item_answers');
                $q->with('media');
            }])
            ->first();
        if($studentTest->status !=="finish"){
            return Response::status('failure')->code(422)->result(["Not allowed to score the test!"]);
        }

        $answers=[];
        foreach($studentTest->student_test_answers as $row){
            if($row->test_questions->question_item->question_item_type!="essay"){
                $iscorrect = $row->test_questions->question_item->question_item_answers->filter(function($val,$key) use($row){
                    return $val['answer'] == $row->answer;
                })[0]['is_correct'];

                $answers[]=[
                    "question"=>$row->test_questions->question_item->question,
                    "question_type"=>$row->test_questions->question_item->question_item_type,
                    "student_answer"=>$row->answer,
                    "is_correct"=>$iscorrect,
                    "score"=>$iscorrect==true?$row->test_questions->question_item->score:0,
                    "media" => $row->media,
                    "question_items"=>$row->test_questions->question_item->question_item_answers,

                ];
            }else{
                $answers[]=[
                    "question"=>$row->test_questions->question_item->question,
                    "question_type"=>$row->test_questions->question_item->question_item_type,
                    "student_answer"=>$row->answer,
                    "is_correct"=>null,
                    "score"=>null,
                    "media" => $row->media,
                    "question_items"=>null,
                ];
            }
        }
        $result = [
            "title" => $studentTest->test->title,
            "subject" => $studentTest->test->subject->name,
            "learning_contract_main_topic" => $studentTest->test->learning_contract_information->main_topic,
            "learning_contract_sub_topic" => $studentTest->test->learning_contract_information->sub_topic,
            "student_name" => $studentTest->student->name,
            "student_profile_picture" => $studentTest->student->student_detail->profile_picture,
            "question"=>$answers
        ];
        return Response::status('success')->result($result);
    }

    public function submitTestScoring(Request $request){
        $testId = $request->test_id;
        $studentId = $request->student_id;
        $score = $request->score;

        // Validation
        $validator = Validator::make($request->all(), [
            'test_id' => 'required|numeric',
            'student_id' => 'required|numeric',
            'score' => 'required|numeric'
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
        $studentTest = StudentTest::where('test_id',$testId)
            ->where('student_id',$studentId)
            ->with('student.schools.school_minimum_competency')
            ->with('test.subject')
            ->first();

        if($studentTest->student->grade > 9){
             $minimum_competency = $studentTest->student->schools->first()->school_minimum_competency->value;
        }else{
             $minimum_competency = $studentTest->test->subject->minimum_competency;
        }

        $studentTest->score = $score;
        $studentTest->save();

        $testAttempt =  TestAttempt::create([
            'test_id' => $testId,
            'student_id' => $studentId,
            'score' => $score,
            'status' => $score<$minimum_competency?"remedial":"passed",

        ]);

        return Response::status('success')->code(200)->result([]);
    }


}
