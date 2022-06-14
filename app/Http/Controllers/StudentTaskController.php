<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Utils\Ordinal;
use Illuminate\Support\Facades\Auth;
use App\Models\Assignment;
use App\Models\SchoolMinimumCompetency;
use App\Models\StudentAssignment;
use App\Models\Media;
use App\Utils\FileSize;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class StudentTaskController extends Controller
{
    /**
     * TASK
     *
     */
    public function task(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $classroomId = $request->query('classroom_id');
        $subjectId = $request->query('subject_id');

        $assignments = Assignment::where('classroom_id', $classroomId)->where('subject_id', $subjectId)
        ->whereHas('student_assignments', function($q) use($student){
            $q->where('student_id', $student->id);
            $q->whereIn('status', ['unbegin', 'late']);
        })->get();

        $result = [];
        if(count($assignments) > 0){
            foreach($assignments as $assignment){
                $dataPush = [
                    'name' => $assignment->title,
                    'schedule' => Carbon::parse($assignment->deadline)->format('l, d F Y'),
                ];

                array_push($result, $dataPush);
            }
        }

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * FORUM TASK
     *
     */
    public function forumTask(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->query('subject_id');
        $search = $request->input('q');

        if ($student->schools()->first()->name == 'SD IIBS' || $student->schools()->first()->name == 'SMP IIBS'){
            $minimumCompetency = Subject::where('id', $subjectId)->first()->minimum_competency;
        }
        else{
            $minimumCompetency = SchoolMinimumCompetency::where('school_id', $student->schools()->first()->id)->first()->value;
        }

        // School Year
        $year = $student->classrooms()->wherepivot('is_active', true)->where('classroom_type', 'regular')->first()->pivot->school_year;

        // Query
        $assignments = Assignment::query()
            ->where('classroom_id', $classroomId)->where('subject_id', $subjectId)//->where('school_year', $year)
            ->whereHas('student_assignments', function ($q) use($student){
                $q->where('student_assignments.student_id', $student->id);
            })
            ->with(['student_assignments' => function($q) use($student){
                $q->select(DB::raw('max(id) as id'),
                            'assignment_id', 'student_id',
                            DB::raw('min(deadline) as deadline'),
                            DB::raw('min(status) as status'),
                            DB::raw('bool_or(is_remedial) as is_remedial'),
                            DB::raw('max(created_at) as created_at'));
                $q->where('student_id', $student->id);
                $q->orderBy('created_at');
                $q->groupBy('assignment_id', 'student_id');
            }])
            ->with('learning_contract_information')
            ->orderByDesc('created_at');

        if ($search != null){
            $assignments = $assignments->where('title', 'ilike', '%'.$search.'%')->paginate(10);
        }
        else{
            $assignments = $assignments->paginate(10);
        }

        $records = [];
        foreach($assignments->items() as $assignment){
            $studentAssignment = $assignment->student_assignments->first();

            // Progress
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

            // Remedial
//            $remedial = false;
//            if($assignment->student_assignments->count() > 0){
//                $score = $assignment->student_assignments[0]->score;
//                if($score !== null){
//                    $remedial = $score < $minimumCompetency;
//                }
//            }

            // Is remedial
            if ($studentAssignment->is_remedial === null){
                $remed = false;
            }
            else{
                $remed = $studentAssignment->is_remedial;
            }

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

            $dataPush = [
                'assignment_id' => $assignment->id,
                'student_assignment_id' => $studentAssignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'material' => $assignment->learning_contract_information->main_topic,
                'deadline' => Carbon::parse($studentAssignment->deadline)->format('l, d F Y G:i'),
                'total_point' => $assignment->total_point,
                'attachment' => $attachment,
                'status' => [
                    'raw' => $studentAssignment->status,
                    'formatted' => $progress
                ],
                'score' => $studentAssignment->score,
                'is_remedial' => $remed,
            ];

            array_push($records, $dataPush);
        }

        $result = [
            'minimum_competency' => $minimumCompetency,
            'paginate' => [
                'total_page' => $assignments->lastPage(),
                'total_records' => $assignments->total(),
                'current_page' => $assignments->currentPage()
            ],
            'records' => $records
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * FORUM TASK PROGRESS
     *
     */
    public function forumTaskProgress(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $classroom = $student->current_classroom();
        $subjectId = $request->query('subject_id');

        $assignments = Assignment::where('classroom_id', $classroom->id)->where('subject_id', $subjectId)
        ->with(['student_assignments' => function($q) use($student){
            $q->where('student_id', $student->id);
        }])->paginate(10);

        // Records
        $records = [];
        foreach($assignments->items() as $assignment){
            switch($assignment->student_assignments[0]->status){
                case 'finish':
                case 'finished':
                    $status = "Collected";
                    break;
                case 'late':
                    $status = "Late";
                    break;
                case 'unbegin':
                    $status = "You haven't submitted the task";
                    break;
            }

            $dataPush = [
                'task_id' => $assignment->id,
                'name' => $assignment->title,
                'status' => $status,
            ];

            array_push($records, $dataPush);
        }

        // Pagination
        $pagination = [
            'total_page' => $assignments->lastPage(),
            'total_records' => $assignments->total(),
            'current_page' => $assignments->currentPage(),
        ];

        $result = [
            'pagination' => $pagination,
            'records' => $records,
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * FORUM TASK DETAIL
     *
     */
    public function forumTaskDetail(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $taskId = $request->query('task_id');

        $task = Assignment::where('id', $taskId)->with(['student_assignments' => function($q) use($student){
            $q->where('student_id', $student->id);
            $q->orderByDesc('id');
            $q->with('medias');
        }])->with(['medias', 'subject', 'learning_contract_information'])->first();

        // Status
        $status = null;
        if($task->student_assignments[0]->status == 'finish'){
            $status = "Collected";
        }else{
            $status = "Assigned";
        }

        // Task Attachment
        $taskAttachment = [];
        if(count($task->medias) > 0){
            foreach($task->medias as $media){
                $dataPush = [
                    'extension' => $media->extension,
                    'file' => $media->file,
                    'name' => $media->name,
                    'size' => $media->size,
                ];

                array_push($taskAttachment, $dataPush);
            }
        }

        // Student Attachment
        $studentAttachment = [];
        if(count($task->student_assignments[0]->medias) > 0){
            foreach($task->student_assignments[0]->medias as $media){
                $dataPush = [
                    'extension' => $media->extension,
                    'file' => $media->file,
                    'name' => $media->name,
                    'size' => $media->size,
                ];

                array_push($studentAttachment, $dataPush);
            }
        }

        $result = [
            'name' => $task->title,
            'subject' => $task->subject->name,
            'material' => $task->learning_contract_information->main_topic,
            'description' => $task->description,
            'status' => $status,
            'deadline' => Carbon::parse($task->deadline)->format('d F Y'),
            'is_remedial' => $task->student_assignments->first()->is_remedial,
            'finished_at' => Carbon::parse($task->student_assignments[0]->finish_at)->format('l, d F Y G:i'),
            'total_point' => $task->total_point,
            'task_attachment' => $taskAttachment,
            'student_answer' => $task->student_assignments[0]->answer,
            'student_attachment' => $studentAttachment,
            'student_score' => $task->student_assignments->first()->score,
            'revised_at' => Carbon::parse($task->student_assignments[0]->revised_at)->format('l, d F Y G:i')
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * FORUM TASK UPLOAD
     *
     */
    public function forumTaskUpload(Request $request){
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:jpg,png,jpeg,jfif,gif,bmp,tif,tiff,heif,heic,tga,svg,mkv,mp4,mov,wmv,webm,flv,
                                        mp3,flac,caf,aiff,alac,aac,mka,wav,m4a,docx,doc,ppt,pptx,pdf,txt'
        ]);

        if($validator->fails()){
            return Response::status('failure')->code(401)->result($validator->errors());
        }

        $file       = $request->file('file');
        $fileName   = Str::uuid().'_'.time().'.'.$file->getClientOriginalExtension();
        $pathFile   = 'assignment/student/';

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

    /**
     * FORUM TASK SUBMIT
     *
     */
    public function forumTaskSubmit(Request $request){
        // Prerequisites
        $user = Auth::user();
        $student = $user->student;

        // Validate
        $validator = Validator::make($request->all(), [
            'assignment_id' => 'required',
        ]);

        if($validator->fails()){
            return Response::status('failure')->code(401)->result($validator->errors());
        }

        //Query
        $studentAssignment = StudentAssignment::query()
            ->where('assignment_id', $request->assignment_id)
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->first();

        if(!$studentAssignment){
            return Response::status('failure')->code(404)->result(['Data not found!']);
        }

        //Save Student Assignment revised_at if already submitted before
        if($studentAssignment->finish_at != null){
            $studentAssignment->revised_at = Carbon::now()->toDateTimeString();

            $status = $studentAssignment->status;
        }
        else{
            $studentAssignment->finish_at = Carbon::now()->toDateTimeString();

            if ($studentAssignment->deadline < Carbon::now()){
                $status = 'late';
            }
            else{
                $status = 'finish';
            }
        }

        // Save Student Assignment
        $studentAssignment->answer = $request->answer;
        $studentAssignment->status = $status;
        $studentAssignment->save();

        $studentAssignment->medias()->attach($request->input('media_id'));

        return Response::status('success')->code(200)->result([]);
    }
}
