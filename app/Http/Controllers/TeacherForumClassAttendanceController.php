<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Family;
use App\Models\LearningContract;
use App\Models\LearningContractInformation;
use App\Models\Media;
use App\Models\Schedule;
use App\Models\ScheduleAttendance;
use App\Models\Student;
use App\Mail\ReplacementClass\ParentReplacementMailer;
use App\Mail\ReplacementClass\StudentReplacementMailer;

use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Utils\FileSize;
use Carbon\Carbon;
use App\Utils\Paginate;
use App\Utils\Response;
use App\Utils\Ordinal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TeacherForumClassAttendanceController extends Controller
{

    public function header(Request $request){
        $scheduleId = $request->input('schedule_id');

        // Validation
        $validator = Validator::make($request->all(), [
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

        $meeting = null;
        $schedule = Schedule::find($scheduleId);

        if ($schedule != null) {
            if ($schedule->learning_contract_information->exists()) {
                $meeting = Ordinal::number($schedule->learning_contract_information->session) . " meeting";
            } else {
                $meeting = null;
            }
        }

        $result = [
            'first' => $schedule->subject->name,
            'second' => $meeting
        ];

        return Response::status('success')->result($result);
    }

    public function index(Request $request){
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');

        // Validation
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|numeric',
            'classroom_id' => 'required|numeric'
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

        $now = Carbon::now()->toDateString();

        $schedule = Schedule::query()
            ->select(DB::raw('min(id) as id'), DB::raw('min(subject_id) as subject_id'), DB::raw('min(classroom_id) as classroom_id'), 'date')
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->where('date', '<=', $now)
            ->orderBy('date')
            ->groupBy('date')
            ->whereNull('deleted_at')
            ->get();

        // Foreach
        $i = 1;
        $records = [];
        foreach ($schedule as $item){
            $old = ScheduleAttendance::query()->where('id', $item->id)->whereIn('status', ['sick', 'present', 'absent'])->get();
            if (!$old){
                $status = true;
            }
            else{
                $status = false;
            }


            $inc = $i++;
            $dataRecords = [
                'schedule_id' => $item->id,
                'name' => Ordinal::number($inc)." Meeting", // Nama meeting sesuai lc (session+meeting) atau auto increment
                'subject' => $item->subject->name,
                'schedule' => [
                    'day' => Carbon::parse($item->date)->format('l'),
                    'date' => Carbon::parse($item->date)->format('d F Y'),
                ],
                'all_present' => $status,
                'filter_sort' => $inc
            ];
            array_push($records, $dataRecords);
        }

        $final = collect($records)->sortByDesc('filter_sort')->toArray();
        $page = Paginate::paginator($final);

        $result = [
            'pagination' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage(),
            ],
            'records' => array_values($page->items())
        ];

        return Response::status('success')->result($result);
    }

    public function info(Request $request){
        $scheduleid = $request->input('schedule_id');

        // Validation
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'required|numeric',
        ]);

        if ($validator->fails()){
            $errors =  $validator->errors();
            $error = [];
            foreach ($errors->all() as $message) {
                $dataPush = $message;
                array_push($error, $dataPush);
            }

            return Response::status('failure')->code(401)->result($error);
        }

        $schedule = Schedule::find($scheduleid);

        // Additional Validation
        if ($schedule == null){
            return Response::status('failure')->result(['Unexpected error']);
        }

        $result = [
            'name' => Ordinal::number($schedule->learning_contract_information->session)." meeting",
            'subject' => $schedule->subjects->name,
            'schedule' => [
                'day' => Carbon::parse($schedule->date)->format('l'),
                'date' => Carbon::parse($schedule->date)->format('d F Y')
            ]
        ];

        return Response::status('success')->result($result);
    }

    public function mainTopic(Request $request){
        $user = Auth::user();
        $teacher = $user->teacher;
        $subjectId = $request->input('subject_id');
        $semester = $request->input('semester');

        // School year
        $userTeacher = Teacher::query()
            ->with(['teacher_classrooms' => function($query){
                $query->orderByDesc('school_year');
            }])
            ->where('id', $teacher->id)
            ->first();

        $year = $userTeacher->teacher_classrooms->first()->pivot->school_year;

        $lc = LearningContract::query()
            ->with(['learning_contract_informations' => function($query){
                $query->select(DB::raw('min(id) as id'), DB::raw('min(learning_contract_id) as learning_contract_id'), 'main_topic');
                $query->orderBy('main_topic');
                $query->groupBy('main_topic');
            }])
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $subjectId)
            ->where('school_year', $year)
            ->where('semester', $semester)
            ->first();

        if (!$lc){
            return Response::status('failure')->code(204)->result("Couldn't find any matching data");
        }

        $result = [];
        foreach ($lc->learning_contract_informations as $item){
            $dataResult = [
                'learning_contract_id' => $item->learning_contract_id,
                'learning_contract_information_id' => $item->id,
                'main_topic' => $item->main_topic,
            ];

            array_push($result, $dataResult);
        }
        if ($result == []){
            $result = null;
        }

        return Response::status('success')->result($result);
    }

    public function subTopic(Request $request){
        $lciId = $request->input('learning_contract_information_id');

        $lci = LearningContractInformation::find($lciId);
        $lcis = LearningContractInformation::query()
            ->select(DB::raw('min(id) as id'), DB::raw('min(learning_contract_id) as learning_contract_id'), 'sub_topic')
            ->with('learning_contract')
            ->where('main_topic', $lci->main_topic)
            ->groupBy('sub_topic')
            ->orderBy('sub_topic')
            ->get();

        $result = [];
        foreach ($lcis as $item){
            $dataResult = [
                'learning_contract_id' => $item->learning_contract_id,
                'learning_contract_information_id' => $item->id,
                'sub_topic' => $item->sub_topic
            ];
            array_push($result, $dataResult);
        }

        return Response::status('success')->result($result);
    }

    public function submitTopic(Request $request){
        $mainTopic = $request->input('main_topic');
        $subTopic = $request->input('sub_topic');
        $user = Auth::user();
        $teacher = $user->teacher;
        $scheduleId = $request->input('schedule_id');
        $lci = $request->input('learning_contract_information_id');
        $lc = $request->input('learning_contract_id');

        $firstSchedule = Schedule::find($scheduleId);
        $schedules = Schedule::query()->where('date', $firstSchedule->date)
            ->where('classroom_id', $firstSchedule->classroom_id)
            ->where('teacher_id', $firstSchedule->teacher_id)
            ->where('subject_id', $firstSchedule->subject_id)
            ->where('school_year', $firstSchedule->school_year)
            ->get();

        // Update Schedule LCI and LC
        foreach ($schedules as $schedule){
            $schedule->learning_contract_id = $lc;
            $schedule->learning_contract_information_id = $lci;
            $schedule->save();
        }

        return Response::status('success')->result();
    }

    public function attendance(Request $request){
        // Prerequisites
        $scheduleId = $request->schedule_id;

        // Query
        $schedule = Schedule::query()
            ->where('id', $scheduleId)
            ->first();

        $students = $schedule->classrooms->students()
            ->wherePivot('is_active', true)
            ->orderBy('name')->get();

        // Foreach
        $records = [];
        foreach ($students as $item){
            $studentAttendance = ScheduleAttendance::query()->where('schedule_id', $scheduleId)->where('student_id', $item->id)->first(); //$item->scheduleattendances()->where('schedule_id', $scheduleId);
            $classroom = $item->classrooms()->wherePivot('is_active', true)->where('classroom_type', 'regular')->first();
            switch ($studentAttendance != null) {
                case true :
                    $status = $studentAttendance->status;
                    $note = null;
                    break;
                case false :
                    $status = null;
                    $note = null;
                    break;
                default :
                    break;
            }

            switch ($status){
                case 'present':
                    $note = null;
                    break;
                case 'sick' : case 'permit': case 'absent' :
                    $note = $studentAttendance->notes;
                    break;
                default:
                    break;
            }

            $dataRecords = [
                'student_id' => $item->id,
                'nis' => $item->nis,
                'name' => $item->name,
                'profile' => $item->student_detail->profile_picture,
                'grade' => $classroom->grade,
                'status' => $status,
                'note' => $note
            ];
            array_push($records, $dataRecords);
        }

        return Response::status('success')->result($records);
    }

    public function uploadMedia(Request $request){
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:jpg,jpeg,jfif,heic,png,pdf'
        ]);

        if($validator->fails()){
            return Response::status('failure')->code(401)->result($validator->errors());
        }

        // Set filename and path
        $file       = $request->file('file');
        $fileName   = Str::uuid().'_'.time().'.'.$file->getClientOriginalExtension();
        $pathFile   = 'attendance_permit_letter/';

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

    public function teacherAttendance(Request $request){
        $user = Auth::user();
        $teacher = $user->teacher;
        $scheduleId = $request->input('schedule_id');

        $teacherAttend = TeacherAttendance::query()
            ->where('schedule_id', $scheduleId)
            ->where('teacher_id', $teacher->id)
            ->first();


        if ($teacherAttend != null) {
            $result = [
                'schedule_id' => $scheduleId,
                'teacher_id' => $teacher->id,
                'status' => $teacherAttend->status,
                'notes' => $teacherAttend->notes
            ];
        }
        elseif($teacherAttend == null){
            $result = [
                'schedule_id' => $scheduleId,
                'teacher_id' => $teacher->id,
                'status' => null,
                'notes' => null,
            ];
        }

        return Response::status('success')->result($result);
    }

    public function teacherSubmitAttendance(Request $request){
        $user = Auth::user();
        $teacher = $user->teacher;
        $scheduleId = $request->input('schedule_id');
        $status = $request->input('notes');
        $notes = $request->input('status');

        $teacherAttend = TeacherAttendance::updateOrCreate([
            'teacher_id' => $teacher->id,
            'schedule_id' => $scheduleId,
        ], [
            'notes' => $status,
            'status' => $notes
        ]);

        $result = [
            'schedule_id' => $scheduleId,
            'teacher_id' => $teacher->id,
            'status' => $status,
            'notes' => $notes
        ];

        return Response::status('success')->code(201)->result($result);
    }

    public function submitattendance(Request $request){
        $studentId = $request->input('student_id');
        $scheduleId = $request->input('schedule_id');
        $mediaId = $request->input('media_id');
        $status = $request->input('status');
        $notes = $request->input('notes');

        if($mediaId === []){
            $mediaId = null;
        }

        $newScheduleAttendance = ScheduleAttendance::updateOrCreate([
            'student_id' => $studentId,
            'schedule_id' => $scheduleId
        ], [
            'media_id' => $mediaId,
            'status' => $status,
            'notes' => $notes
        ]);

        $result = [
            'schedule_id' => $scheduleId,
            'student_id' => $studentId,
            'status' => $status,
            'notes' => $notes
        ];

        return Response::status('success')->code(201)->result($result);
    }

    public function replacement(Request $request){
        $scheduleid = $request->input('schedule_id');

        // Query
        $schedule = Schedule::find($scheduleid);

        $whereNotPresent = $schedule->schedule_attendances
            ->whereIn('status', ['absent', 'sick', 'permit'])
            ->sortBy('name');

//        $students = $schedule->attendances()
//            ->wherepivotin('status', ['absent', 'sick', 'permit'])
//            ->orderBy('name', 'asc')->get();

        $students = $schedule->classrooms->students()
            ->wherePivot('is_active', true)
            ->wherePivotIn('student_id', $whereNotPresent->pluck('student_id'))
            ->orderBy('name')->get();

        // Foreach
        $records = [];
        foreach ($students as $item){
            // get classroom (for grade)
            $classroom = $item->classrooms()->wherePivot('is_active', true)->where('classroom_type', 'regular')->first();

            // Get replacement submitted schedule date & time
            $replacement = $item->replacement_schedules()->wherePivot('old_schedule_id', $scheduleid)->wherePivot('student_id', $item->id)->first();
            if ($replacement != null){
                $status = true;
                $time = [
                    'day' => Carbon::parse($replacement->date)->format('l'),
                    'date' => Carbon::parse($replacement->date)->format('d F Y'),
                    'start_at' => Carbon::parse($replacement->start_at)->format('G:i'),
                    'end_at' => Carbon::parse($replacement->end_at)->format('G:i')
                ];
            }
            else{
                $status = false;
                $time = null;
            }

            $dataRecords = [
                'student_id' => $item->id,
                'name' => $item->name,
                'nis' => $item->nis,
                'profile' => $item->student_detail->profile_picture,
                'grade' => $classroom->grade,
                'has_replacement' => $status,
                'time' => $time
            ];
            array_push($records, $dataRecords);
        }

        return Response::status('success')->result($records);
    }

    public function submitreplacement(Request $request){
        // Prerequisites
        $user = Auth::user();
        $teacher = $user->teacher;
        $studentIds = $request->student_id;
        $scheduleId = $request->schedule_id;

        // Query
        $students = Student::wherein('id', $studentIds)
            ->with(['classrooms' => function($query){
                $query->wherePivot('is_active', '=', 'true');
                $query->orderBy('id');

                // For Validate Purpose
                $query->with(['schedules' => function($q){
                    $q->select(DB::raw('min(id) as id'),
                        DB::raw('min(classroom_id) as classroom_id'), 'date',
                        DB::raw('min(start_at) as start_at'),
                        DB::raw('min(end_at) as end_at'))
                        ->orderBy('date')
                        ->groupBy('date');
                }]);
            }])
            ->get();

        $schedule = Schedule::with(['classrooms' => function($query){
                $query->with('major');
            }])
            ->with(['subjects' => function($q){
                $q->first();
            }])
            ->with(['learning_contract_information' => function($q) {
                $q->with('learning_contract');
            }])
            ->where('id', $scheduleId)->first();

        // Validate Schedule
        if (!$schedule){
            return Response::status('failure')->code(204)->result("Couldn't find schedule");
        }
        if(!$schedule->learning_contract_information){
            return Response::status('failure')->result("learning_contract_information must be filled first");
        }

        /**
         * VALIDATE DUPLICATE STUDENT SCHEDULES
         */
        // Data request
        $requestTimeStartSchedule   = Carbon::parse($request->input('start_at'))->toTimeString();
        $requestTimeEndSchedule     = Carbon::parse($request->input('end_at'))->toTimeString();
        // Validate start_at shouldn't be bigger than end_at
        if ($requestTimeStartSchedule > $requestTimeEndSchedule){
            return Response::status('failure')->result("invalid time");
        }
        // 1st each students
        foreach ($students as $student){
            // 2nd each student classrooms where is_active == true
            foreach ($student->classrooms  as $classroom){
                // 3rd each classroom schedules
                foreach ($classroom->schedules as $item){
                    // Data for validate
                    $dateSchedule       = $item->date;
                    $startTimeSchedule  = Carbon::parse($item->start_at)->toTimeString();
                    $endTimeSchedule    = Carbon::parse($item->end_at)->toTimeString();

                    // Validating the student who already have same schedule date
                    if ($request->date == $dateSchedule && $requestTimeStartSchedule <= $endTimeSchedule && $requestTimeStartSchedule >= $startTimeSchedule){
                        return Response::status('failure')->result("Student ".$student->name." already had schedule on that time");
                    }
                    if ($request->date == $dateSchedule && $requestTimeEndSchedule <= $endTimeSchedule && $requestTimeEndSchedule >= $startTimeSchedule){
                        return Response::status('failure')->result("Student ".$student->name." already had schedule on that time");
                    }

                }
            }
        }

        // Data for new classroom
        $oldClass = $schedule->classrooms;
        $year = $oldClass->students()->wherePivot('is_active', true)->first()->pivot->school_year;
        $explodedYear = explode('/', $year);
        $base = "CR";
        $type = strtoupper($oldClass->learning_type);

        if($oldClass->major->slug == 'science' || $oldClass->major->slug == 'sociology'){
            $baseReplacementCode = $base.$oldClass->name.strtoupper($oldClass->major->slug);

            if (!Classroom::whereLike('code', $baseReplacementCode)->first()){
                $newClassCode = $baseReplacementCode."-".$type."-".$explodedYear[0]."-1";
            }
            else{
                $firstQuery = Classroom::whereLike('code', $baseReplacementCode)->strIntOrderBy('code', 'desc')->first();
                $explodedCode = explode('-', $firstQuery->code);
                $newExplodedCode = $explodedCode[3] + 1;

                $newClassCode = $baseReplacementCode."-".$type."-".$explodedYear[0]."-".$newExplodedCode;
            }
        }
        else{
            $baseReplacementCode = $base.$oldClass->name."-".$type;

            if (!Classroom::whereLike('code', $baseReplacementCode)->first()){
                $newClassCode = $baseReplacementCode."-".$type."-".$explodedYear[0]."-1";
            }
            else{
                $firstQuery = Classroom::whereLike('code', $baseReplacementCode)->strIntOrderBy('code', 'desc')->first();
                $explodedCode = explode('-', $firstQuery->code);
                $newExplodedCode = $explodedCode[3] + 1;

                $newClassCode = $baseReplacementCode."-".$type."-".$explodedYear[0]."-".$newExplodedCode;
            }
        }
        $newClassName = $oldClass->name.
            "-".$schedule->subjects->name.
            ", ".Ordinal::number($schedule->learning_contract_information->session)." Meeting";


        // Insert new classroom
        $newClass = new Classroom();
        $newClass->name = $newClassName;
        $newClass->teacher_id = $teacher->id;
        $newClass->classroom_type = 'replacement';
        $newClass->code = $newClassCode;
        $newClass->grade = $oldClass->grade;
        $newClass->major_id = $oldClass->major_id;
        $newClass->capacity = count($studentIds);
        $newClass->save();

        // insert new student to the classroom
        foreach ($students as $student){
            $active = $student->classrooms()->wherePivot('is_active', true)->where('classroom_type', 'regular')->orderByDesc('school_year')->first();
            $attributes = Arr::except($active->pivot->getAttributes(), ['is_leader' ,'classroom_id', 'student_id']);
            $attributes['is_leader'] = false;
            $newClass->students()->attach($student->id, $attributes);
        }

        // insert school or dorm relations
        if($oldClass->schools()->exists()){
            $oldId = $oldClass->schools()->first()->id;
            $newClass->schools()->attach($oldId);
        }
        elseif ($oldClass->dormitories()->exists()){
            $oldId = $oldClass->dormitories()->first()->id;
            $newClass->dormitories()->attach($oldId);
        }

        // Insert Teacher Classroom
        $attr = [
            'school_year' => $year
        ];
        $newClass->teacher_classrooms()->attach($teacher->id, $attr);

        // New Schedule
        $latestId = DB::table('schedules')->orderByDesc('id')->first()->id;
        $newSchedule = $schedule->replicate();
        $newSchedule->id = $latestId + 1;
        $newSchedule->classroom_id = $newClass->id;
        $newSchedule->date = $request->date;
        $newSchedule->start_at = $request->start_at;
        $newSchedule->end_at = $request->end_at;
        $newSchedule->schedule_type = 'once';
        $newSchedule->save();

        // Insert student_replacement Table
        $extraAttrSchedule = [
            'schedule_id' => $newSchedule->id,
            'old_schedule_id' => $scheduleId
        ];
        $newClass->replacement_students()->attach($studentIds, $extraAttrSchedule);

        //Sending email
        $result = [];
        foreach ($students as $student){
            $data = [
                'student' => $student->name,
                'date' => Carbon::parse($request->input('date'))->toDateString(),
                'start_at' => Carbon::parse($request->input('start_at'))->toTimeString(),
                'end_at' => Carbon::parse($request->input('end_at'))->toTimeString()
            ];


            Mail::to($student->email)->send(new StudentReplacementMailer($data));

            $families = $student->student_families->whereIn('relationship_role', ['vice', 'parents']);
            foreach ($families as $item){
                Mail::to($item->family->email)->send(new ParentReplacementMailer($data));
            }

            $result[] = [
                'student' => $student->name,
                'schedule' => [
                    'date' => Carbon::parse($newSchedule->date)->format('d F Y'),
                    'start_at' => $newSchedule->start_at,
                    'end_at' => $newSchedule->end_at
                ]
            ];
        }

        return Response::status('success')->result($result);
    }
}
