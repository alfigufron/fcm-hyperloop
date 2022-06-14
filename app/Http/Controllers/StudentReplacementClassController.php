<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\Classroom;
use App\Models\Subject;
use App\Utils\Response;
use Illuminate\Http\Request;

class StudentReplacementClassController extends Controller
{
    /**
     * REPLACEMENT CLASS HEADER
     * 
     */
    public function header(Request $request) {
        $subject = Subject::find($request->query('subject_id'));

        $result = [
            'subject' => $subject->name
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * REPLACEMENT CLASS 
     * 
     */
    public function replacementClass(Request $request) {
        $subject = Subject::find($request->query('subject_id'));

        $result = [
            'subject' => $subject->name
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * SEMESTER
     * 
     */
    public function semester(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $classroomId = $request->query('classroom_id');

        $classroom = Classroom::where('id', $classroomId)->whereHas('classroom_students', function($q) use($student){
            $q->where('student_id', $student->id);
        })->with('schedules.learning_contract_information.learning_contract')->first();

        $result = [];
        $semester = [];
        foreach($classroom->schedules as $schedule){
            $LC = $schedule->learning_contract_information->learning_contract;
            if(!in_array($LC->semester, $semester)){
                array_push($semester, $LC->semester);

                $dataPush = [
                    'learning_contract_id' => $LC->id,
                    'grade' => $LC->grade,
                    'semester' => $LC->semester
                ];

                array_push($result, $dataPush);
            }
        }

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * DETAIL REPLACEMENT CLASS
     * 
     */
    public function detail(Request $request) {
        $classroom_id = $request->query('classroom_id');
        $semester = $request->query('semester');
        $teacher_id = $request->query('teacher_id');

        $student = Auth::user()->student;

        // $student_list = $student->replacement_classrooms()
        //     ->wherePivot('classroom_id', $classroom_id)
        //     ->get();

        $classroom = Classroom::find($classroom_id);
        $classroom = Classroom::where('grade', $classroom->grade)
            ->where('major_id', $classroom->major_id)
            ->where('teacher_id', $teacher_id)
            ->where('classroom_type', 'replacement')
            ->first();

        $student_list = $classroom->students;
        $schedule = $classroom->schedules[0];
        $lci = $schedule->learning_contract_information;
        
        // $schedules = $student->replacement_schedules
        //     ->where('semester', $semester)
        //     ->where('classroom_id', $classroom_id)
        //     ->all();

        $result = [];

        foreach ($student_list as $key => $list) {
            $result[$key] = [
                'main_topic' => $lci->main_topic,
                'sub_topic' => $lci->sub_topic,
                'date' => $schedule->date,
                'student_list' => $list->pluck('name')
            ];
        }

        return response()->json($result);
    }
}
