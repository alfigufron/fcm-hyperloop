<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\LearningContract;
use App\Models\Media;
use App\Models\StudentAssignment;
use App\Models\Subject;
use App\Utils\FileSize;
use App\Utils\Paginate;
use App\Utils\Response;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TeacherForumTaskController extends Controller
{
    public function header(Request $request){
        $subjectid = $request->subject_id;
        $taskid = $request->task_id;

        // Validation
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|numeric',
            'task_id' => 'numeric'
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
        $material = null;
        $subject = Subject::find($subjectid);
        $task = Assignment::find($taskid);

        // Validation
        if($subject == null){
            return Response::status('failure')->result(["Couldn't find subject"]);
        }
        if($task != null) {
            if ($task->learning_contract_information()->exists()) {
                $material = $task->learning_contract_information->main_topic;
            }
            else {
                return Response::status('failure')->result(['Unexpected Error']);
            }
        }

        $result = [
            'first' => $subject->name,
            'second' => $material
        ];

        return Response::status('success')->result($result);
    }

    public function taskList(Request $request){
        $classroomid = $request->input('classroom_id');
        $subjectid = $request->input('subject_id');
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

        // School Year
        $classroom = Classroom::with('students')->where('id', $classroomid)->first();
        foreach ($classroom->students as $student){
            $true = $student->pivot->where('is_active', true)->first();
            $year = $true->school_year;
        }

        // Query
        $tasks = Assignment::with('student_assignments')
            ->where('classroom_id', $classroomid)
            ->where('subject_id', $subjectid)
            ->where('school_year', $year)
            ->orderByDesc('created_at')
            ->wherenull('deleted_at');
        if ($search != null){
            $tasks = $tasks->where('title', 'ilike', '%'.$search.'%');
        }

        // Paginate
        $page = $tasks->paginate('10');
        $items = $page->items();

        // Foreach
        $records = [];
        foreach ($items as $item){
            // Progress
            $student = $item->student_assignments;
            $finish = count($student->wherein('status', ['finish', 'late']));

            $progress = [
                'done' => $finish,
                'from' => count($student)
            ];

            // Attachments
            $attachment = [];
            foreach ($item->medias as $media){
                $dataAttach = [
                    'file' => $media->file,
                    'extension' => $media->extension,
                    'name' => $media->name,
                    'size' => $media->size
                ];
                array_push($attachment, $dataAttach);
            }

            $dataRecords = [
                'id' => $item->id,
                'title' => $item->title,
                'main_topic' => $item->learning_contract_information->main_topic,
                'description' => $item->description,
                'deadline' => Carbon::parse($item->deadline)->format('d F Y'),
                'total_point' => $item->total_point,
                'attachment' => $attachment,
                'progress' => $progress
            ];

            array_push($records, $dataRecords);
        }

        $result = [
          'pagination' => [
              'total_page' => $page->lastPage(),
              'total_record' => $page->total(),
              'current_page' => $page->currentPage()
          ],
          'records' => $records
        ];

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
        $pathFile   = 'assignment/teacher/';

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
            ->where('grade', $classroom->grade)
            ->where('teacher_id', $teacherId)
            ->where('school_year', $year)
            ->orderBy('id')
            ->first();

        if (!$lc){
            return Response::status('failure')->code(204)->result("Couldn't find any data");
        }

        $result = [];
        foreach ($lc->learning_contract_informations as $lci){
            $result[] = [
                'learning_contract_information_id' => $lci->id,
                'main_topic' => $lci->main_topic
            ];
        }

        return Response::status('success')->result($result);
    }

    public function detail(Request $request){
        $taskId = $request->input('task_id');

        $task = Assignment::query()->with('medias')->where('id', $taskId)->first();

        if (!$task){
            return Response::status('failure')->result("Couldn't find any matching task");
        }

        $attachment = [];
        foreach ($task->medias as $media){
            $dataAttachment = [
                'media_id' => $media->id,
                'file' => $media->file,
                'extension' => $media->extension,
                'name' => $media->name,
                'size' => $media->size
            ];
            array_push($attachment, $dataAttachment);
        }

        $result = [
            'task_id' => $task->id,
            'title' => $task->title,
            'main_topic' => $task->learning_contract_information->main_topic,
            'description' => $task->description,
            'deadline' => Carbon::parse($task->deadline)->format('d F Y'),
            'attachment' => $attachment,
        ];

        return Response::status('success')->result($result);
    }

    public function create(Request $request){
        $mediaId = $request->input('media_id');
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');
        $learningContractInformationId = $request->input('learning_contract_information_id');

        // Validate
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|numeric',
            'subject_id' => 'required|numeric',
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

        // Create new Task
        $newTask = new Assignment();
        $newTask->subject_id = $subjectId;
        $newTask->classroom_id = $classroomId;
        $newTask->learning_contract_information_id = $learningContractInformationId;
        $newTask->title = $request->input('title');
        $newTask->description = $request->input('description');
        $newTask->total_point = $request->input('total_point');
        $newTask->deadline = $request->input('deadline_date')." ".$request->input('deadline_time');
        $newTask->link = $request->input('link');
        $newTask->save();
        $newTask->medias()->sync($mediaId);

        //Insert Student
        $classroom = Classroom::where('id', $classroomId)->with('students')->first();
        $students = $classroom->students()->wherePivot('is_active', true);
        $studentAssignment = [];
        foreach ($students->get() as $student){
            $dataStudentAssignment = [
                'student_id' => $student->id,
                'assignment_id' => $newTask->id,
                'answer' => null,
                'score ' => null,
                'status' => 'unbegin',
                'finish_at' => null,
                'is_remedial' => null
            ];
            array_push($studentAssignment, $dataStudentAssignment);
        }
        $newTask->student_assignments()->createMany($studentAssignment);

        // Additional data for new task
        $newTask->school_year = $students->first()->pivot->school_year;
        $newTask->save();

        return Response::status('success')->code(201)->result(null);
    }

    public function delete(Request $request){
        $taskId = $request->input('task_id');

        $now = Carbon::now();
        DB::table('assignment_media')
            ->where('assignment_id', $taskId)
            ->update(['deleted_at' => $now]);

        $task = Assignment::find($taskId);
        $task->student_assignments()->delete();
        $task->assignment_attempts()->delete();
        $task->delete();


        return Response::status('success')->result(null);
    }

    // Dumped
    public function progress(Request $request){
        $classroomid = $request->classroom_id;
        $subjectid = $request->subject_id;

        // Validate
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required',
            'subject_id' => 'required'
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

        // School Year
        $classroom = Classroom::find($classroomid);
        foreach ($classroom->students as $student){
            $true = $student->pivot->where('is_active', true)->first();
            $year = $true->school_year;
        }

        // Query
        $tasks = Assignment::where('classroom_id', $classroomid)
            ->where('subject_id', $subjectid)
            ->where('school_year', $year)
            ->wherenull('deleted_at');

        // Paginate
        $page = $tasks->paginate('10');
        $items = $page->items();

        if($items == null){
            return Response::status('failure')->code(204)->result(['Task Not Found']);
        }

        foreach ($items as $item){
            $student = $item->student_assignments;
            $finish = count($student->wherein('status', ['finish', 'late']));
            $records[] = [
                'title' => $item->title,
                'material' => $item->learning_contract_information->main_topic,
                'progress' => [
                    'done' => $finish,
                    'from' => count($item->student_assignments)
                ],
                'remedial' => 'Still Static'
            ];
        }

        $result = [
            'pagination' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage()
            ],
            'records' => $records
        ];

        return Response::status('success')->result($result);
    }

    public function progressInfo(Request $request){
        $taskid = $request->task_id;
        $task = Assignment::find($taskid);

        $result = [
            'title' => $task->title,
            'material' => $task->learning_contract_information->main_topic,
            'description' => $task->description,
            'deadline' => Carbon::parse($task->deadline)->format('d F Y'),
            'total_point' => $task->total_point
        ];

        return Response::status('success')->result($result);
    }

    public function progressDetail(Request $request){
        $taskId = $request->task_id;

        $task = Assignment::query()
            ->with(['student_assignments' => function($query){
                $query->select(DB::raw('max(id) as id'),
                    DB::raw('min(assignment_id) as assignment_id'), 'student_id',
                    DB::raw('max(score) as score'), DB::raw('max(status) as status'),
                    DB::raw('max(finish_at) as finish_at'), DB::raw('bool_or(is_remedial) as is_remedial'),
                    DB::raw('max(created_at) as created_at'));
                $query->orderBy('created_at');
                $query->groupBy('student_id');
            }])
            ->where('id', $taskId)
            ->first();

        $page = Paginate::paginator($task->student_assignments);
        $items = $page->items();

        // Foreach
        $records = [];
        foreach ($items as $item){
            // Grade
            $student = $item->students;
            $classroom = $student->classrooms()->wherePivot('is_active', true)->where('classroom_type', 'regular')->first();

            // Status student submit
            switch ($item->status){
                case 'finish':
                    $status = 'collected';
                    $late = 'on time';
                    break;
                case 'late':
                    $status = 'collected';
                    $late = 'collected late';
                    break;
                case 'unbegin':
                    $status = 'not yet collected';
                    $late = 'not yet collected';
                    break;
                default:
                    break;
            }

            // Status Remedial
            switch ($item->is_remedial){
                case null:
                case false:
                    $isRemedial = false;
                    break;
                case true:
                    $isRemedial = true;
                    break;
            }

            $dataRecords = [
                'student_id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'profile' => $student->student_detail->profile_picture,
                'grade' => $classroom->grade,
                'score' => $item->score,
                'submitted' => $status,
                'status_submitted' => $late,
                'is_remedial' => $isRemedial,
                'finished_at' => Carbon::parse($item->finish_at)->format("l, d F Y G:i"),
            ];
            array_push($records, $dataRecords);
        }

        $finalRecord = [];
        $finalRecord = collect($records)->sortBy('name')->toArray();

        $result = [
            'pagination' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage(),
            ],
            'records' => array_values($finalRecord)
        ];

        return Response::status('success')->result($result);
    }

    public function progressStudent(Request $request){
        $taskId = $request->task_id;
        $studentId = $request->student_id;

        // Validation
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|numeric',
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

        $task = Assignment::query()
            ->with(['student_assignments' => function($query) use ($studentId) {
                $query->where('student_id', $studentId);
                $query->orderByDesc('id');
            }])
            ->where('id', $taskId)
            ->first();

        $student = $task->student_assignments->first();
        $studentAttachments = $student->medias;
        $taskMedias = $task->medias;
        $studentAttach = [];
        $taskAttach = [];

        // Student Attachment
        foreach ($studentAttachments as $studentAttachment){
            $dataStudentAttach = [
                'file' =>$studentAttachment->file,
                'extension' => $studentAttachment->extension,
                'name' => $studentAttachment->name,
                'size' => $studentAttachment->size
            ];
            array_push($studentAttach, $dataStudentAttach);
        }

        // Task Attachment
        foreach ($taskMedias as $taskMedia){
            $dataTaskAttach = [
                'file' => $taskMedia->file,
                'name' => $taskMedia->name,
                'size' => $taskMedia->size
];
            array_push($taskAttach, $dataTaskAttach);
        }

        switch ($student->status){
            case 'finish':
                $status = 'collected';
                $late = 'collected on time';
                break;
            case 'late':
                $status = 'collected';
                $late = 'collected late';
                break;
            case 'unbegin':
                $status = 'not yet collected';
                $late = 'not yet collected';
                break;
            default:
                break;
        }

        $result = [
            'profile' => [
                'name' => $student->students->name,
                'profile' => $student->students->profile_picture,
            ],
            'name' => $task->title,
            'nis' => $student->students->nis,
            'subject' => $task->subject->name,
            'material' => $task->learning_contract_information->main_topic,
            'description' => $task->description,
            'total_point' => $task->total_point,
            'task_attachment' => $taskAttach,
            'student_attachment' => $studentAttach,
            'submitted' => $status,
            'status_submitted' => $late,
            'deadline' => Carbon::parse($task->deadline)->format("l, d F Y"),
            'finish_at' => Carbon::parse($student->finish_at)->format("l, d F Y G:i"),
            'revised_at' => Carbon::parse($student->revised_at)->format("l, d F Y G:i"),
            'score' => $student->score,
            'notes' => $student->notes,
        ];

        return Response::status('success')->result($result);
    }

    public function inputScoresStudent(Request $request){
        // Prerequisites
        $taskId = $request->input('task_id');
        $studentId = $request->input('student_id');

        // Validation
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|numeric',
            'student_id' => 'required|numeric',
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
        $assignment = Assignment::query()
            ->with(['student_assignments' => function($query) use($studentId){
                $query->orderByDesc('id');
                $query->where('student_id', $studentId);
            }])
            ->where('id', $taskId)
            ->first();

        // Get the latest student assignment
        $studentAssignment = $assignment->student_assignments->first();

        // Validate Student Assingment status
        if ($studentAssignment->status !== 'finish'){
            return Response::status('failure')->result("Not Allowed!");
        }

        $studentAssignment->score = $request->input('score');
        $studentAssignment->save();

        return Response::status('success')->result();
    }
}
