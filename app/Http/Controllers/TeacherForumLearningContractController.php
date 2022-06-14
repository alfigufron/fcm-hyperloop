<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\LearningContract;
use App\Models\Subject;
use App\Utils\Paginate;
use App\Utils\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class  TeacherForumLearningContractController extends Controller
{
    /**
     * Teacher Learning Contract
     */
    public function header(Request $request){
        // Prerequisites
        $subjectId = $request->subject_id;
        $classroomId = $request->classroom_id;
        $semester = $request->semester;

        // Validation
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|numeric',
            'classroom_id' => 'required|numeric',
            'semester' => 'numeric|nullable'
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

        // Query
        $subject = Subject::find($subjectId);
        $classroom = Classroom::find($classroomId);

        if($subject == null){
            return Response::status('failure')->result(["Couldn't find Subject"]);
        }
        if ($classroom == null){
            return Response::status('failure')->result(["Couldn't find Classroom"]);
        }

        $result = [
            'first' => $subject->name,
            'second' => [
                'grade' => $classroom->grade,
                'semester' => $semester
            ]
        ];

        return Response::status('success')->result($result);
    }

    public function detail(Request $request){
        // Prerequisites
        $user = Auth::user();
        $teacher = $user->teacher;
        $subjectId = $request->subject_id;
        $classroomId = $request->classroom_id;
        $semester = $request->semester;

        // Validation
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|numeric',
            'classroom_id' => 'required|numeric',
            'semester' => 'required|numeric'
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

        // School Year
        $classroom = Classroom::find($classroomId);
        foreach ($classroom->students as $student){
            $true = $student->pivot->where('is_active', true)->first();
            $year = $true->school_year;
        }

        // Query
        $lc = LearningContract::query()
            ->with(['learning_contract_informations' => function($query){
                $query->where('category', 'Lesson');
                $query->orWhere('category', 'lesson');
                $query->orderBy('basic_competency', 'asc');
            }])
            ->where('grade', $classroom->grade)
            ->where('school_year', $year)
            ->where('semester', $semester)
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $subjectId)
            ->first();

        if (!$lc){
            return Response::status('failure')->code(204)->result(["Couldn't find any matching learning contract"]);
        }

        $record = [];
        $grouped = $lc->learning_contract_informations->groupBy('main_topic');
        foreach ($grouped as $item){
            $subTopic = [];
            foreach ($item as $value){
                $dataValue = [
                    'basic_competency' => $value->basic_competency,
                    'sub_topic' => $value->sub_topic
                ];
                array_push($subTopic, $dataValue);
            }

            $data = [
                'main_topic' => $item->first()->main_topic,
                'sub' => $subTopic
            ];
            array_push($record, $data);
        }

        $page = Paginate::paginator($record);

        $result = [
            'paginate' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage()
            ],
            'records' => $record
        ];

        return Response::status('success')->result($result);
    }
}
