<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Classroom;
use App\Models\LearningContract;
use App\Models\Schedule;
use App\Models\Subject;
use App\Utils\Ordinal;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;

class StudentLearningContractController extends Controller
{
    /**
     * LERANING CONTRACT HEADER
     * 
     */
    public function header(Request $request){
        $subject = Subject::find($request->query('subject_id'));

        $result = [
            'subject' => $subject->name
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * LEARNING CONTRACT
     * 
     */
    public function learningContract(Request $request){
        $student = Auth::user()->student;
        $classroom = $student->current_classroom();
        
        $semester = Helper::getSemesterByClass($classroom->grade);

        $result = [];
        foreach ($semester as $key => $item) {
            $result[$key] = [
                'grade' => $classroom->grade,
                'semester' => $item
            ];
        }

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * LEARNING CONTRACT SEMESTER
     * 
     */
    public function semester(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $classroomId = $request->query('classroom_id');
        $subjectId = $request->query('subject_id');

        $schedules = Schedule::select('learning_contract_information_id')->where('classroom_id', $classroomId)->where('subject_id', $subjectId)
        ->whereHas('schedule_attendances', function($q) use($student){
            $q->where('student_id', $student->id);
        })->with('learning_contract_information.learning_contract')->groupBy('learning_contract_information_id')->get();

        $result = [];
        $semester = [];
        foreach($schedules as $schedule){
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
     * LEARNING CONTRACT DETAIL
     * 
     */
    public function detail(Request $request){
        $subject_id = $request->query('subject_id');
        $semester = $request->query('semester');
        $classroom_id = $request->query('classroom_id');
        $teacher_id = $request->query('teacher_id');

        $classroom = Classroom::find($classroom_id);
        $grade = $classroom->grade;
        
        $school_year = $classroom->classroom_students[0]
            ->school_year;

        $lc = LearningContract::where('subject_id', $subject_id)
            ->where('semester', $semester)
            ->where('school_year', $school_year)
            ->where('grade', $grade)
            ->where('teacher_id', $teacher_id)
            ->first();

        if (!$lc)
            return Response::status('failure')->code(404)->result(null);


        $lci = $lc->learning_contract_informations->groupBy('main_topic');

        $result = [];
        foreach ($lci as $key => $main_topic) {
            $main_data = ['main_topic' => $key];

            $sub_data = [];
            foreach ($main_topic as $key => $item)
                $sub_data[$key] = [
                    'basic_competency' => $item->basic_competency,
                    'sub_topic' => $item->sub_topic
                ];

            $main_data['sub'] = $sub_data;
            array_push($result, $main_data);
        }

        return Response::status('success')->code(200)->result($result);
    }
}
