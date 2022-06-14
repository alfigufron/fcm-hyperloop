<?php


namespace App\Http\Controllers;

use App\Imports\QuestionImport;

use App\Models\Media;
use App\Models\Question;
use App\Models\QuestionItem;
use App\Models\QuestionItemAnswer;
use App\Models\QuestionItemDiscuss;
use App\Models\Subject;

use App\Utils\FileSize;
use App\Utils\Paginate;
use App\Utils\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TeacherQuestionBankController
{
    public function index(){
        $user = Auth::user();
        $teacher = $user->teacher;

        // Teacher School or dorm
        if($teacher->schools()->exists()){
            $schoolOrDorm = $user->teacher->schools()->first();
            $school = $user->teacher->schools()->first();
        }
        elseif ($teacher->dormitories()->exists()){
            $schoolOrDorm =  $user->teacher->dormitories()->first();
            $school = $schoolOrDorm->schools;
        }

        // Grade
        switch ($school->levels->slug){
            case 'sd':
                $semester = [1,2,3,4,5,6,7,8,9,10,11,12];
                $grade = [1,2,3,4,5,6];
                break;
            case 'smp':
                $semester = [1,2,3,4,5,6];
                $grade = [7,8,9];
                break;
            case 'sma':
                $semester = [1,2,3,4,5,6];
                $grade = [10,11,12];
                break;
            default:
                break;
        }

        // Subjects // way 2 from teacher subjects
        foreach($teacher->subjects as $item){
            $subjects[] = [
                'subject_id' => $item->id,
                'name' => $item->name
            ];
        }

        $result = [
            'subjects' => $subjects,
            'grade' => $grade,
            'semester' => $semester
        ];

        return Response::status('success')->result($result);
    }

    public function grade(){
        // Prerequisites
        $user = Auth::user();
        $teacher = $user->teacher;

        // Get schools
        if ($teacher->schools()->exists()){
            $school = $teacher->schools()->first();
        }
        elseif ($teacher->dormitories()->exists()){
            $dorm = $teacher->dormitories()->first();
            $school = $dorm->schools;
        }
        else{
            return Response::status('failure')->result("teacher doesn't have school or dorm relation");
        }

        // Get grade by school
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
                $grade = null;
                break;
        }

        // Result
        $result = [
            'grade' => $grade
        ];

        return Response::status('success')->result($result);
    }

    public function semester(Request $request){
        // Prerequisites
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

        // Get Semester
        switch ($grade){
            case '1' :
            case '7' :
            case '10':
                $semester = [1,2];
                break;
            case '2' :
            case '8' :
            case '11':
                $semester = [3,4];
                break;
            case '3' :
            case '9' :
            case '12':
                $semester = [5,6];
                break;
            case '4' :
                $semester = [7,8];
                break;
            case '5' :
                $semester = [9,10];
                break;
            case '6' :
                $semester = [11,12];
                break;
            default:
                $semester = null;
                break;
        }

        // Result
        $result = [
            'semester' => $semester
        ];

        return Response::status('success')->result($result);
    }

    public function subject(){
        // Prerequisites
        $user = Auth::user();
        $teacher = $user->teacher;

        // Get school or dorm
        if ($teacher->schools()->exists()){
            $schoolOrDorm = $teacher->schools()->first();

            $rawSubjects = $schoolOrDorm->subjects()->orderBy('name', 'asc')->get();
        }
        elseif ($teacher->dormitories()->exists()){
            $schoolOrDorm = $teacher->dormitories()->first();

            $rawSubjects = $schoolOrDorm->subjects()->orderBy('name', 'asc')->get();
        }
        else{
            return Response::status('failure')->result("Teacher doesn't have school or dorm relation");
        }

        $subjects = [];
        foreach ($rawSubjects as $subject){
            $dataSubject = [
                'subject_id' => $subject->id,
                'name' => $subject->name
            ];

            array_push($subjects, $dataSubject);
        }

        //Result
        $result = [
            'subjects' => $subjects
        ];

        return Response::status('success')->result($result);
    }

    public function search(Request $request){
        $title = $request->input('title');
        $grade = $request->input('grade');
        $semester = $request->input('semester');
        $subject_id = $request->input('subject_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'grade' => 'required|numeric',
            'semester' => 'required|numeric',
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
        $questions = Question::with('subjects')
            ->where('subject_id', $subject_id)
            ->where('grade', $grade)
            ->where('semester', $semester);
        if ($title != null){
            $resultQuery = $questions->where('title', 'like', '%'.$title.'%')->get();
        }
        else{
            $resultQuery = $questions->get();
        }

        // Validate
        if (!$resultQuery){
            return Response::status('failure')->code(204)->result(["Couldn't find question"]);
        }

        $page = Paginate::paginator($resultQuery, 10);

        // Foreach
        $records = [];
        foreach ($page->items() as $item){
            $multipleItem = 0;
            $essayItem = 0;
            $checkboxItem = 0;
            $mixAndMatchItem = 0;
            $trueOrFalseItem = 0;
            $question_item_list = "";
            foreach ($item->question_items as $value){
                switch ($value->question_item_type){
                    case "multiple":
                        $multipleItem++;
                        break;
                    case "essay":
                        $essayItem++;
                        break;
                    case "mix_and_match":
                        $raw = QuestionItem::query()
                            ->select(DB::raw('min(id) as id'), DB::raw('min(question_item_type) as question_item_type'), 'mix_and_match_group')
                            ->where('question_item_type', 'mix_and_match')
                            ->where('question_id', $item->id)
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

            $desc = "This Question has$question_item_list";
            if ($desc == "This Question has"){
                $desc = "This Question doesn't have any question";
            }

            // Question related school
            if($item->schools()->exists()){
                $schoolOrDorm = $item->schools()->first()->name;
            }
            elseif ($item->dormitories()->exists()){
                $schoolOrDorm = $item->dormitories()->first()->name;
            }
            else{
                $schoolOrDorm = null;
            }

            $dataRecords = [
                'question_id' => $item->id,
                'teacher' => $item->teacher->name,
                'title' => $item->title,
                'subject' => $item->subjects->name,
                'description' => $desc,
                'school_or_dorm' => $schoolOrDorm
            ];

            array_push($records, $dataRecords);
        }

        // Result
        $result = [
            'paginate' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage()
            ],
            'records' => $records
        ];

        return Response::status('success')->result($result);
    }

    public function detail(Request $request){
        $questionId = $request->input('question_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|numeric',
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
        $question = Question::with(['question_items' => function($query){
                $query->with(['question_item_answers' => function($q){
                    $q->with('media');
                }]);
                $query->with(['question_item_discussses' => function($q){
                    $q->with('media');
                }]);
                $query->with('media');
            }])
            ->with('subjects')
            ->where('id', $questionId)
            ->first();

        // Validate Question
        if (!$question){
            return Response::status('failure')->code(204)->result(["Couldn't find question"]);
        }

        // Foreach Question Item
        $questionItems = [];
        foreach ($question->question_items as $item) {
            $questionType = $item->question_item_type;

            switch ($questionType){
                case 'multiple':
                case 'essay':
                case 'checkbox':
                case 'true_or_false':
                    /// Answers
                    $questionItemAnswers = [];
                    foreach ($item->question_item_answers as $answer) {
                        // Answer Media
                        $QuestionItemAnswerMedias = [];
                        foreach ($answer->media as $media){
                            $dataQuestionItemAnswerMedias = [
                                'media_id' => $media->id,
                                'file' => $media->file,
                                'name' => $media->name,
                                'size' => $media->size,
                            ];
                            array_push($QuestionItemAnswerMedias, $dataQuestionItemAnswerMedias);
                        }

                        // Answer
                        $dataAnswer = [
                            'answer_id' => $answer->id,
                            'answer' => $answer->answer,
                            'media' => $QuestionItemAnswerMedias,
                        ];
                        array_push($questionItemAnswers, $dataAnswer);
                    }

                    if ($questionItemAnswers == []){
                        $questionItemAnswers = null;
                    }

                    /// Media
                    $questionItemMedia = [];
                    foreach ($item->media as $media){
                        $dataQuestionMedias = [
                            'media_id' => $media->id,
                            'file' => $media->file,
                            'name' => $media->file,
                            'size' => $media->size,
                        ];
                        array_push($questionItemMedia, $dataQuestionMedias);
                    }

                    /// Discusses
                    $questionItemDiscussion = [];
                    foreach ($item->question_item_discussses as $discuss){
                        // Discuss Media
                        $questionItemDiscussMedia = [];
                        foreach ($discuss->media as $media){
                            $dataQuestionItemDiscussMedias = [
                                'media_id' => $media->id,
                                'file' => $media->file,
                                'name' => $media->file,
                                'size' => $media->size,
                            ];
                            array_push($questionItemDiscussMedia, $dataQuestionItemDiscussMedias);
                        }

                        // Discuss
                        $dataDiscuss = [
                            'question_item_discuss_id' => $discuss->id,
                            'discussion' => $discuss->discuss,
                            'media' => $questionItemDiscussMedia
                        ];
                        array_push($questionItemDiscussion, $dataDiscuss);
                    }

                    /// Question
                    $dataQuestionItem = [
                        'question_item_id' => $item->id,
                        'type' => $item->question_item_type,
                        'question' => $item->question,
                        'level' => $item->level,
                        'score' => $item->score,
                        'media' => $questionItemMedia,
                        'answers' => $questionItemAnswers,
                        'discussion' => $questionItemDiscussion,
                    ];
                    array_push($questionItems, $dataQuestionItem);
                    break;
                default:
                    break;
            }
        }

        /// Mix And Match
        // 1st Query mix_and_match_group
        $rawMixMatch = QuestionItem::query()
            ->select(DB::raw('min(id) as id'),
                DB::raw('min(question_item_type) as question_item_type'),
                DB::raw('min(question_id) as question_id'),'mix_and_match_group')
            ->with('question_item_answers')
            ->where('question_id', $questionId)
            ->where('question_item_type', 'mix_and_match')
            ->groupby('mix_and_match_group')
            ->get();


        foreach ($rawMixMatch as $item){
            $valMixMatch = $item->mix_and_match_group;
            $mixMatch = QuestionItem::with('question_item_answers')
                ->with('test_question_item')
                ->with('question_item_discussses')
                ->where('question_id', $questionId)
                ->where('question_item_type', 'mix_and_match')
                ->where('mix_and_match_group', $valMixMatch)->get();

            $rawQuestion = [];
            $rawAnswer = [];
            foreach ($mixMatch as $nestedItem){
                /// Answer
                foreach ($nestedItem->question_item_answers as $answer){
                    // Answer Media
                    $QuestionItemAnswerMedias = [];
                    foreach ($answer->media as $media){
                        $dataQuestionItemAnswerMedias = [
                            'media_id' => $media->id,
                            'file' => $media->file,
                            'name' => $media->name,
                            'size' => $media->size
                        ];
                        array_push($dataQuestionItemAnswerMedias, $dataQuestionItemAnswerMedias);
                    }

                    // Answer
                    $dataRawAnswer = [
                        'answer' => $answer->answer,
                        'media' => $QuestionItemAnswerMedias
                    ];
                    array_push($rawAnswer, $dataRawAnswer);
                }

                /// Discusses
                $questionItemDiscussion = [];
                foreach ($item->question_item_discussses as $discuss){
                    // Discuss Media
                    $questionItemDiscussMedia = [];
                    foreach ($discuss->media as $media){
                        $dataQuestionItemDiscussMedias = [
                            'media_id' => $media->id,
                            'file' => $media->file,
                            'extension' => $media->extenstion
                        ];
                        array_push($questionItemDiscussMedia, $dataQuestionItemDiscussMedias);
                    }

                    // Discuss
                    $dataDiscuss = [
                        'question_item_discuss_id' => $discuss->id,
                        'discussion' => $discuss->discuss,
                        'media' => $questionItemDiscussMedia
                    ];
                    array_push($questionItemDiscussion, $dataDiscuss);
                }

                ///  Media
                $questionItemMedia = [];
                foreach ($nestedItem->media as $media){
                    $dataQuestionItemMedia = [
                        'media_id' => $media->id,
                        'file' => $media->file,
                        'name' => $media->name,
                        'size' => $media->size,
                    ];
                    array_push($questionItemMedia, $dataQuestionItemMedia);
                }

                /// Question
                $dataRawQuestion = [
                    'question_item_id' => $nestedItem->id,
                    'question' => $nestedItem->question,
                    'score' => $nestedItem->score,
                    'level' => $nestedItem->level,
                    'media' => $questionItemMedia,
                    'discuss' => $questionItemDiscussion
                ];
                array_push($rawQuestion, $dataRawQuestion);
            }

            $rawFull = [
                'type' => 'mix_and_match',
                'questions' => $rawQuestion,
                'answers' => $rawAnswer
            ];

            array_push($questionItems, $rawFull);
        }

        $result = [
            'question_id' => $question->id,
            'code' => $question->code,
            'title' => $question->title,
            'subject' => $question->subjects->name,
            'grade' => $question->grade,
            'semester' => $question->semester,
            'question_items' => $questionItems
        ];

        return Response::status('success')->result($result);
    }

    public function media(Request $request){
        $type = $request->input('type');

        // Validate
        $validator = Validator::make($request->all(), [
            'type' => 'required',
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

        // Defining media type
        if (in_array($type, ['image', 'video', 'audio', 'docs'])) {
            switch ($type){
                case 'image':
                    $mediaType = ['jpg', 'png', 'jpeg', 'jfif', 'gif', 'bmp', 'tif', 'tiff', 'heif', 'heic', 'tga'];
                    break;
                case 'video':
                    $mediaType = ['mkv', 'mp4', 'mov', 'wmv', 'webm', 'flv'];
                    break;
                case 'audio':
                    $mediaType = ['mp3', 'caf', 'flac', 'wav', 'm4a', 'aiff', 'alac', 'aac', 'mka'];
                    break;
                case 'docs' :
                    $mediaType = ['docx', 'doc', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'pdf', 'txt'];
                    break;
            }
        }
        else{
            return Response::status('failure')->result(["Unexpected type value"]);
        }

        // Query
        $medias = Media::query()
            ->where('file', 'ilike', '%'.'/question-bank/media/'.'%')
            ->whereIn('extension', $mediaType);

        // Validate Media
        if (count($medias->get()) == 0){
            return Response::status('failure')->code(204)->result(["Couldn't find any media"]);
        }

        // Paginate
        $page = $medias->paginate('10');

        // Foreach Data
        foreach ($page->items() as $item){
            $records[] = [
                'media_id' => $item->id,
                'name' => $item->name,
                'file' => $item->file,
                'size' => $item->size,
                'extension' => $item->extension
            ];
        }

        $result = [
            'paginate' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage()
            ],
            'records' => $records
        ];

        return Response::status('success')->result($result);
    }

    public function mediaUpload(Request $request){
        // Validate
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:jpg,png,jpeg,jfif,gif,bmp,tif,tiff,heif,heic,tga,svg,mkv,mp4,mov,wmv,webm,flv,
                                        mp3,flac,caf,aiff,alac,aac,mka,wav,m4a,docx,doc,xls,xlsx,csv,ppt,pptx,pdf,txt'
        ]);

        if($validator->fails()){
            return Response::status('failure')->code(401)->result($validator->errors());
        }

        // Set filename and path
        $file       = $request->file('file');
        $fileName   = Str::uuid().'_'.time().'.'.$file->getClientOriginalExtension();
        $pathFile   = '/question-bank/media/';

        // Upload File
        $file->storeAs($pathFile, $fileName, 'gcs');
        $disk = Storage::disk('gcs');
        $path = $disk->url($pathFile.$fileName);

        // Insert data to database
        $newMedia = new Media();
        $newMedia->name = $request->file->getClientOriginalName();
        $newMedia->file = $path;
        $newMedia->extension = $file->getClientOriginalExtension();
        $newMedia->category = 'assignment';
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

    public function updateQuestionItemMedia(Request $request){
        // Prerequisites
        $mediaId = $request->input('media_id');
        $questionItemId = $request->input('question_item_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'media_id' => 'required|numeric',
            'question_item_id' => 'required|numeric'
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
        $questionItem = QuestionItem::with('media')
            ->where('id', $questionItemId)
            ->first();

        if($questionItem->with_media == 'yes') {
            $questionItem->media()->attach($mediaId);

            return Response::status('success')->code(201)->result()->setStatusCode(201);
        }

        return Response::status('success')->result();
    }

    public function updateQuestionItemAnswerMedia(Request $request){
        // Prerequisites
        $mediaId = $request->input('media_id');
        $questionItemAnswerId = $request->input('question_item_answer_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'media_id' => 'required|numeric',
            'question_item_answer_id' => 'required|numeric'
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
        $questionItemAnswer = QuestionItemAnswer::with('media')
            ->where('id', $questionItemAnswerId)
            ->first();

        if($questionItemAnswer->with_media == 'yes') {
            $questionItemAnswer->media()->attach($mediaId);

            return Response::status('success')->code(201)->result()->setStatusCode(201);
        }

        return Response::status('success')->result();
    }

    public function updateQuestionItemDiscussMedia(Request $request){
        // Prerequisites
        $mediaId = $request->input('media_id');
        $questionItemDiscussId = $request->input('question_item_discuss_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'media_id' => 'required|numeric',
            'question_item_discuss_id' => 'required|numeric'
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
        $questionItemDiscuss = QuestionItemDiscuss::with('media')
            ->where('id', $questionItemDiscussId)
            ->first();

        if($questionItemDiscuss->with_media == 'yes') {
            $questionItemDiscuss->media()->attach($mediaId);

            return Response::status('success')->code(201)->result()->setStatusCode(201);
        }

        return Response::status('success')->result();
    }

    public function deleteQuestionItemMedia(Request $request){
        // Prerequisites
        $mediaId = $request->input('media_id');
        $questionItemId = $request->input('question_item_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'media_id' => 'required|numeric',
            'question_item_id' => 'required|numeric'
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
        $questionItem = QuestionItem::with('media')
            ->where('id', $questionItemId)
            ->first();
        $questionItem->media()->detach($mediaId);

        return Response::status('success')->result();
    }

    public function deleteQuestionItemAnswerMedia(Request $request){
        // Prerequisites
        $mediaId = $request->input('media_id');
        $questionItemAnswerId = $request->input('question_item_answer_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'media_id' => 'required|numeric',
            'question_item_answer_id' => 'required|numeric'
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
        $questionItemAnswer = QuestionItemAnswer::with('media')
            ->where('id', $questionItemAnswerId)
            ->first();
        $questionItemAnswer->media()->detach($mediaId);

        return Response::status('success')->result();
    }

    public function deleteQuestionItemDiscussMedia(Request $request){
        // Prerequisites
        $mediaId = $request->input('media_id');
        $questionItemDiscussId = $request->input('question_item_discuss_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'media_id' => 'required|numeric',
            'question_item_discuss_id' => 'required|numeric'
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

        $questionItemDiscuss = QuestionItemDiscuss::with('media')
            ->where('id', $questionItemDiscussId)
            ->first();
        $questionItemDiscuss->media()->detach($mediaId);

        return Response::status('success')->result();
    }

    // Upload below here is creating 1 paket soal
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
            return Response::status('failure')->code(422)->result(["Couldn't find subject, check if there any typos in excel"])->setStatusCode(422);
        }
        if ($code == null){
            return Response::status('failure')->code(422)->result(["code is required"])->setStatusCode(422);
        }
        if ($title == null){
            return Response::status('failure')->code(422)->result(["title is required"])->setStatusCode(422);
        }
        if ($grade == null){
            return Response::status('failure')->code(422)->result(["grade is required"])->setStatusCode(422);
        }
        if ($semester == null){
            return Response::status('failure')->code(422)->result(["semester is required"])->setStatusCode(422);
        }

        // New Validate Specific grade and semester
        switch ($grade){
            case '1' : case '2' : case '3' : case '4' : case '5' : case '6' :
                $level = 'sd';
                $gradeCorrect = [1,2,3];
                if (!in_array($semester, [1,2,3,4,5,6,7,8,9,10,11,12])){
                    return Response::status('failure')->code(422)->result(["Invalid semester value"])->setStatusCode(422);
                }
                break;
            case '7': case '8': case '9':
                $level = 'smp';
                $gradeCorrect = [1,2,3,4];
                if (!in_array($semester, [1,2,3,4,5,6])){
                    return Response::status('failure')->code(422)->result(["Invalid semester value"])->setStatusCode(422);
                }
                break;
            case '10': case '11': case '12':
                $level = 'sma';
                $gradeCorrect = [1,2,3,4,5];
                if (!in_array($semester, [1,2,3,4,5,6])){
                    return Response::status('failure')->code(422)->result(["Invalid semester value"])->setStatusCode(422);
                }
                break;
            default:
                return Response::status('failure')->code(422)->result(["Invalid grade value"])->setStatusCode(422);
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
                            return Response::status('failure')->code(422)->result(["Some field answer is missing or 'answer_media' value is wrong"])->setStatusCode(422);
                        }
                    }

                    // Determine if question has media or not
                    if ($row['question_media'] == 'no'){
                        $questionValidate = $row['question'] != null;
                        if ($questionValidate == false){
                            return Response::status('failure')->code(422)->result(["Some field question is missing or 'question_media' value is wrong"])->setStatusCode(422);
                        }
                    }

                    // Correct must be present
                    if(!in_array($row['correct'], $gradeCorrect)){
                        return Response::status('failure')->code(422)->result(["Some field correct is incorrect value, check if there any typos"])->setStatusCode(422);
                    }
                    break;

                case "true_or_false":
                    // Creating true or false string not boolean
                    $answer1 = $row['answer_1'] == true ? 'true' : 'false';
                    $answer2 = $row['answer_2'] == true ? 'true' : 'false';

                    // Validate 2 answer can only be present
                    $validate = $answer1 != null && $answer2 != null;
                    if ($validate == false) {
                        return Response::status('failure')->code(422)->result(["some field 'answer_1' or 'answer_2' in true or false question is missing or 'answer_media' value is wrong"])->setStatusCode(422);
                    }

                    // Validate correct must be present whether it 1 or 2
                    if (!in_array($row['correct'], [1,2])){
                        return Response::status('failure')->code(422)->result(["Some field correct is incorrect value, check if there any typos"])->setStatusCode(422);
                    }
                    break;

                case 'checkbox':
                    // Determine if answer has media
                    if ($row['answer_media'] == 'no') {
                        $validate = $row['answer_1'] != null && $row['answer_2'] != null && $row['answer_3'] != null && $row['answer_4'] != null && $row['answer_5'] != null;
                        if ($validate == false) {
                            return Response::status('failure')->code(422)->result(["Some field answer is missing or 'answer_media' value is wrong"])->setStatusCode(422);
                        }
                    }

                    // Checking correct can't be can be lower than 1 nor higher than 5
                    if (!in_array($row['correct'], [1,2,3,4,5])){
                        return Response::status('failure')->code(422)->result(["Some field correct is incorrect value, check if there any typos"])->setStatusCode(422);
                    }

                    // Determine if question has media
                    if ($row['question_media'] == 'no'){
                        $questionValidate = $row['question'] != null;
                        if ($questionValidate == false){
                            return Response::status('failure')->code(422)->result(["Some field question is missing or 'question_media' value is wrong"])->setStatusCode(422);
                        }
                    }
                    break;

                case 'mix_and_match':
                    // Determine if question has media
                    if ($row['question_media'] == 'no'){
                        $questionValidate = $row['question'] != null;
                        if ($questionValidate == false){
                            return Response::status('failure')->code(422)->result(["Some field question is missing or 'question_media' value is wrong"])->setStatusCode(422);
                        }
                    }

                    // Determine if answer has media
                    if ($row['answer_media'] == 'no'){
                        $validateAnswerMixMatch = $row['answer_1'] !== null;
                        if ($validateAnswerMixMatch == false){
                            return Response::status('failure')->code(422)->result("field 'answer_1' in 'mix and match' type must be filled")->setStatusCode(422);
                        }
                    }

                    // Correct value must be equal to 1
                    if ($row['correct'] !== 1){
                        return Response::status('failure')->code(422)->result("the correct answer value must be 1")->setStatusCode(422);
                    }
                    break;

                case 'essay':
                    // Determine if question has media
                    if ($row['question_media'] == 'no'){
                        $questionValidate = $row['question'] != null;
                        if ($questionValidate == false){
                            return Response::status('failure')->code(422)->result(["Some field question is missing or 'question_media' value is wrong"])->setStatusCode(422);
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
                return Response::status('failure')->result("Score can't be higher than 100")->setStatusCode(422);
            case $sumScore < 100:
                return Response::status('failure')->result("Score can't be lower than 100")->setStatusCode(422);
            default:
                break;
        }

        // Create new Question
        $user = Auth::user();
        $teacher = $user->teacher;

        $newQuestion = new Question();
        $newQuestion->code = $code;
        $newQuestion->title = $title;
        $newQuestion->subject_id = $forumSubject->id;
        $newQuestion->grade = $grade;
        $newQuestion->semester = $semester;
        $newQuestion->teacher_id = $teacher->id;
        $newQuestion->save();

        // Insert Relation Question is it schools or dormitories
        $schoolId = $request->input('school_id');

        if ($teacher->schools()->exists()){
//            $SchoolOrDorm = $teacher->schools()->first();
            $newQuestion->schools()->attach($schoolId);
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

    public function deleteQuestion(Request $request){
        // Prerequisites
        $questionId = $request->input('question_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|numeric'
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
        $question = Question::query()
            ->with(['question_items' => function($query){
                $query->with('test_question_item'); // For checking if the question have already a test
                // Related data
                $query->with(['question_item_answers' => function($q){
                    $q->with('media');
                }]);
                $query->with(['question_item_discussses' => function($q){
                    $q->with('media');
                }]);
                $query->with('media');
            }])
            ->where('id', $questionId)
            ->first();

        // Validate question
        if ($question === null){
            return Response::status('failure')->result("Selected question not exists or has been deleted")->setStatusCode(422);
        }

        if ($question->question_items->first()->test_question_item->isNotEmpty()){
            return Response::status('failure')->result ('the selected question already has a test!')->setStatusCode(422);
        }

        // Deleting questions with related items
        foreach($question->question_items as $qi){
            foreach ($qi->question_item_answers as $qia){
                $qia->media()->detach();
                $qia->delete();
            }

            foreach ($qi->question_item_discussses as $qid){
                $qid->media()->detach();
                $qid->delete();
            }

            $qi->media()->detach();
            $qi->delete();
        }
        $question->delete();

        return Response::status('success')->result();
    }
}
