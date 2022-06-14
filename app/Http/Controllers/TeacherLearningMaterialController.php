<?php


namespace App\Http\Controllers;

use App\Models\LearningContract;
use App\Models\Lesson;
use App\Models\Media;
use App\Models\Subject;
use App\Models\Teacher;
use App\Utils\FileSize;
use App\Utils\Paginate;
use App\Utils\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;


class TeacherLearningMaterialController extends Controller
{
    public function index(){
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
                $semester = null;
                $grade = null;
                break;
        }

//        // Subjects // way 1 from the school or dormitories
//        foreach ($schoolOrDorm->subjects as $item){
//            $subjects[] = [
//                'subject_id' => $item->id,
//                'name' => $item->name
//            ];
//        }

        // Subjects // way 2 from teacher subjects
        $subjects = [];
        foreach($teacher->subjects as $item){
            $dataSubjects = [
                'subject_id' => $item->id,
                'name' => $item->name
            ];
            array_push($subjects, $dataSubjects);
        }

        $result = [
            'subjects' => $subjects,
            'grade' => $grade,
            'semester' => $semester
        ];

        return Response::status('success')->result($result);
    }

    public function header(Request $request){
        // Prerequisites
        $semester = $request->input('semester');
        $subjectId = $request->input('subject_id');
        $grade = $request->input('grade');

        $subject = Subject::find($subjectId);

        $result = [
            'semester' => $semester,
            'grade' => $grade,
            'subject' => $subject->name,
        ];

        return Response::status('success')->result($result);
    }

    public function list(Request $request){
        // Prerequisites
        $semester = $request->input('semester');
        $subjectId = $request->input('subject_id');
        $grade = $request->input('grade');
        $title = $request->input('title');

        // Validate
        $validator = Validator::make($request->all(), [
            'semester' => 'required|numeric',
            'grade' => 'required|numeric',
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
        $lessons = Lesson::query()
            ->with('subject')
            ->with('learning_contract_informations')
            ->where(function ($query) use($title){
                if ($title != null){
                    $query->where('title', 'like', '%'.$title.'%');
                }
            })
            ->whereNull('classroom_id')
            ->where('subject_id', $subjectId)
            ->where('grade', $grade)
            ->where('semester', $semester)
            ->orderByDesc('created_at')
            ->get();

        // Validate
        if (!$lessons){
            return Response::status('failure')->result("Couldn't find any lessons");
        }

        // Paginate
        $page = Paginate::paginator($lessons, 10);
        $items = $page->items();

        $record = [];
        foreach ($items as $item){
            // Learning Material Attachment
            $attachment = [];
            foreach ($item->medias as $media){
                $dataPush = [
                    'media_id' => $media->id,
                    'file' => $media->file,
                    'name' => $media->name,
                    'size' => $media->size
                ];
                array_push($attachment, $dataPush);
            }

            // Change to null if item doesn't have any attachemnt
            if ($attachment == []){
                $attachment = null;
            }

            // Response
            $dataRecord = [
                'lesson_id' => $item->id,
                'title' => $item->title,
                'subject' => $item->subject->name,
                'description' => $item->description,
                'main_topic' => $item->learning_contract_informations->main_topic,
                'grade' => $item->grade,
                'semester' => $item->semester,
                'attachment' => $attachment
            ];

            array_push($record, $dataRecord);
        }

        $result = [
            'paginate' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage(),
            ],
            'records' => $record
        ];

        return Response::status('success')->result($result);
    }

    // Create get Grade
    public function grade(){
        $user = Auth::user();
        $teacher = $user->teacher;

        if ($teacher->schools()->exists()){
            $level = $teacher->schools()->first()->levels->slug;
        }
        if ($teacher->dormitories()->exists()){
            $level = $teacher->dormitories()->first()->schools->levels->slug;
        }

        switch ($level){
            case "sd":
                $grade = [1,2,3,4,5,6];
                break;
            case "smp":
                $grade = [7,8,9];
                break;
            case "sma":
                $grade = [10,11,12];
                break;
            default:
                break;
        }

        $result = [
            "grade" => $grade
        ];

        return Response::status('success')->result($result);
    }

    // Create get Semester
    public function semester(Request $request){
        $grade = $request->input('grade');

        switch ($grade){
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
            case '4':
                $semester = [7,8];
                break;
            case '5':
                $semester = [9,10];
                break;
            case '6':
                $semester = [11,12];
                break;
        }

        $result = [
            'semester' => $semester
        ];

        return Response::status('success')->result($result);
    }

    // Get Filter Subjects
    public function subject(){
        // Prerequisites
        $user = Auth::user();
        $teacher = $user->teacher;

        // Get School or Dorm
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

        // Result
        $result = [
            'subjects' => $subjects
        ];

        return Response::status('success')->result($result);
    }

    // Create get Subject
    public function createSubject(){
        // Prerequisites
        $user = Auth::user();
        $teacher = $user->teacher;

        // Get Teacher Subject
        $subjects = [];
        foreach ($teacher->subjects as $subject){
            $dataSubjects = [
                'subject_id' => $subject->id,
                'name' => $subject->name
            ];
            array_push($subjects, $dataSubjects);
        }

        // Result
        $result = [
            'subjects' => $subjects
        ];

        return Response::status('success')->result($result);
    }

    // Create get Main topic
    public function createMainTopic(Request $request){
        // Prerequisites
        $user = Auth::user();
        $teacher = $user->teacher;
        $grade = $request->input('grade');
        $subjectId = $request->input('subject_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'grade' => 'required|numeric',
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


        // Get School year
        $a = Teacher::query()
            ->with(['teacher_classrooms' => function($query){
                $query->orderByDesc('school_year');
                $query->where('classroom_type', 'regular');
            }])
            ->where('id', $teacher->id)->first();

        $year = $a->teacher_classrooms->first()->pivot->school_year;

        // Query
        $lc = LearningContract::query()
            ->with(['learning_contract_informations' => function($query){
                $query->select(DB::raw('min(id) as id'), DB::raw('min(learning_contract_id) as learning_contract_id'), 'main_topic');
                $query->groupBy('main_topic');
            }])
            ->where('subject_id', $subjectId)
            ->where('grade', $grade)
            ->where('teacher_id', $teacher->id)
            ->where('school_year', $year)
            ->first();

        // Validate LC
        if (!$lc){
            return Response::status('failure')->code(204)->result("Couldn't find any Learning Contract");
        }

        // Result
        $result = [];
        foreach ($lc->learning_contract_informations as $item) {
            $dataResult = [
                'learning_contract_information_id' => $item->id,
                'main_topic' => $item->main_topic
            ];
            array_push($result, $dataResult);
        }

        return Response::status('success')->result($result);
    }

    public function upload(Request $request){
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
        $pathFile   = 'lesson/';

        // Upload File
        $file->storeAs($pathFile, $fileName, 'gcs');
        $disk = Storage::disk('gcs');
        $path = $disk->url($pathFile.$fileName);

        // Insert data to database
        $newMedia = new Media();
        $newMedia->name = $request->file->getClientOriginalName();
        $newMedia->file = $path;
        $newMedia->extension = $file->getClientOriginalExtension();
        $newMedia->category = 'general';
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

    public function create(Request $request){
        $title = $request->input('title');
        $grade = $request->input('grade');
        $semester = $request->input('semester');
        $description = $request->input('description');
        $learningContractInformationId = $request->input('learning_contract_information_id');
        $subjectId = $request->input('subject_id');
        $mediaId = $request->input('media_id');

        $newLesson = new Lesson();
        $newLesson->subject_id = $subjectId;
        $newLesson->title = $title;
        $newLesson->description = $description;
        $newLesson->learning_contract_information_id = $learningContractInformationId;
        $newLesson->grade = $grade;
        $newLesson->semester = $semester;
        $newLesson->save();
        $newLesson->medias()->attach($mediaId);

        if (!$newLesson->save()){
            return Response::status('failure')->result("Couldn't create lesson");
        }

        return Response::status('success')->result();
     }

    public function detail(Request $request){
        $lessonId = $request->input('lesson_id');

        $lesson = Lesson::query()->with('learning_contract_informations')
            ->with('subject')
            ->where('id', $lessonId)
            ->first();

        if (!$lesson){
            return Response::status('failure')->result("Couldn't find any matching lesson");
        }

        // Attachment
        $attachment = [];
        foreach ($lesson->medias as $media){
            $dataPush =  [
                'media_id' => $media->id,
                'file' => $media->file,
                'name' => $media->name,
                'size' => $media->size,
            ];
            array_push($attachment, $dataPush);
        }

        // Change to null if item doesn't have any attachemnt
        if ($attachment == []){
            $attachment = null;
        }

        // Result
        $result = [
            'lesson_id' => $lesson->id,
            'title' => $lesson->title,
            'subject' => $lesson->subject->name,
            'description' => $lesson->description,
            'main_topic' => $lesson->learning_contract_informations->main_topic,
            'grade' => $lesson->grade,
            'semester' => $lesson->semester,
            'attachment' => $attachment,
        ];

        return Response::status('success')->result($result);
     }
}
