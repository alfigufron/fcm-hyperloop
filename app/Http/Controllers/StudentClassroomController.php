<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Utils\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;

class StudentClassroomController extends Controller
{
    /**
     * LIST
     *
     */
    public function list(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $type = $request->query('type');
        $isSchool = $request->query('school');

        if(!in_array($request->type, ['regular', 'general', 'replacement'])){
            $result = 'Type paramater is invalid';

            return Response::status('failure')->code(422)->result($result);
        }

        $classrooms = Classroom::whereHas('students', function($q) use($student){
            $q->where('student_id', $student->id);
//            $q->where('is_active', TRUE);
        })->where(function($q) use($type, $isSchool){
            // Type
            if($type == 'replacement'){
                $q->where('classroom_type', $type);
            }else{
                $q->whereIn('classroom_type', ['regular', 'matriculation', 'extra']);
            }

            // School or dorm
            if($isSchool == 'true'){
                $q->has('schools');
            }else{
                $q->has('dormitories');
            }
        })->paginate(10);

        // Records
        $records = [];
        foreach($classrooms->items() as $classroom){
            if ($classroom->schools()->exists()){
                $name = "Classroom ".$classroom->name;
            }
            elseif ($classroom->dormitories()->exists()){
                $name = "Dorm Room ".$classroom->name;
            }

            if ($classroom->major->slug != 'general'){
                $name = $name. " (" . strtoupper($classroom->major->slug) . ")";
            }

            $dataPush = [
                'classroom_id' => $classroom->id,
                'name' => $name,
                'type' => [
                    'raw' => $classroom->classroom_type,
                    'formatted' => ucwords($classroom->classroom_type)." Class",
                ],
                'code' => $classroom->code,
                'filter_grade' => $classroom->grade,
                'filter_type' => $classroom->classroom_type,
            ];

            array_push($records, $dataPush);
        }

        $final = collect($records)->sortByDesc('filter_grade')->sortByDesc('filter_type')->toArray();

        // Pagination
        $pagination = [
            'total_page' => $classrooms->lastPage(),
            'total_records' => $classrooms->total(),
            'current_page' => $classrooms->currentPage(),
        ];

        $result = [
            'pagination' => $pagination,
            'records' => array_values($final),
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     *
     * DETAIL
     */
    public function detail(Request $request){
        $classroom = Classroom::find($request->query('id'));

        $result = [
            'id' => $classroom->id,
            'name' => $classroom->name,
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * STUDENT
     *
     */
    public function student(){
        $user = Auth::user();
        $student = $user->student;

        $result = [
            'name' => $student->name,
            'grade' => $student->current_classroom()->grade
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * STUDENT MEMBER
     *
     */
    public function studentMember(Request $request){
        $classroomId = $request->query('classroom_id');

        $classroom = Classroom::where('id', $classroomId)->with(['classroom_students' => function($q){
            $q->where('is_active', TRUE);
            $q->with('student.student_detail');
        }])->first();

        $result = [];
        foreach($classroom->classroom_students as $student){
            $dataPush = [
                'id' => $student->student_id,
                'name' => $student->student->name,
                'profile' => $student->student->student_detail->profile_picture,
                'is_leader' => $student->is_leader,
            ];

            array_push($result, $dataPush);
        }

        return Response::status('success')->code(200)->result($result);
    }
}
