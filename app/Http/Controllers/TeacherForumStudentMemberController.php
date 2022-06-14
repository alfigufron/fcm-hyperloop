<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Subject;
use App\Utils\Paginate;
use App\Utils\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TeacherForumStudentMemberController extends Controller
{
    public function header(Request $request){
        // Prerequisites
        $subjectId = $request->subject_id;

        // Validate request param
        $validator = Validator::make($request->all(), [
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

        // Query
        $subject  = Subject::find($subjectId);

        if($subject == null){
            return Response::status('failure')->result(["Couldn't find subject"]);
        }

        // Result
        $result = [
            'first' => $subject->name
        ];

        return Response::status('success')->result($result);
    }

    // Student Member
    public function index(Request $request){
        $classroomId = $request->classroom_id;
        $sort = $request->input('sort');

        // Validate request param
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'numeric',
            'lesson_id' => 'numeric',
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
        $classroomsStudents = $classroom->students()->wherePivot('is_active', 'true')->wherePivot('deleted_at', null)->get();

        if($sort == null){
            $students = $classroomsStudents->sortBy('id');
        }
        elseif($sort == 'asc'){
            $students = $classroomsStudents->sortBy('name');
        }
        elseif($sort == 'desc') {
            $students = $classroomsStudents->sortByDesc('name');
        }
        else{
            return Response::status('failure')->result(["Unexpected sort value"]);
        }

        // Paginate
        $page = Paginate::paginator($students);
        $total = $page->total();
        $lastpage = $page->lastPage();
        $curpage = $page->currentPage();
        $items = $page->items();

        // Foreach
        $records = [];
        foreach ($items as $item){
            $dataRecords = [
                'student_id' => $item->id,
                'name' => $item->name,
                'profile' => $item->student_detail->profile_picture,
            ];
            array_push($records, $dataRecords);
        }

        $result = [
            'paginate' => [
                'total_page' => $lastpage,
                'total_records' => $total,
                'current_page' => $curpage
            ],
            'records' =>  $records
        ];

        return Response::status('success')->result($result);
    }

    // Delete additional student member // Not fnished
    public function delete(Request $request){
        // Prerequisites
        $classroomId = $request->input('classroom_id');
        $studentid = $request->input('student_id');

        $classroom = Classroom::query()
            ->with('students')
            ->where('id', $classroomId)
            ->first();

        if ($classroom->classroom_type == 'extra') {
            $classroom->students()->detach($studentid);
            return Response::status('success')->result();
        }
        else{
            return Response::status('failure')->result("");
        }

    }
}
