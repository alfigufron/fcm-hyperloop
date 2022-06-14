<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utils\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Lesson;
use App\Models\Student;

class StudentLearningMaterialController extends Controller
{
    public function filter(){
        $dataStudent = Auth::user()->student;
        $student_id = $dataStudent->id;
        $student = Student::where('id',$student_id)
            ->with(['schools'=>function($q){
                $q->with('levels');
                $q->with(['subjects'=>function($q){
                    $q->select('subject_id','code','name');
                }]);
            }])
            ->get();
        $student_level = $student->last()->schools->last()->levels->slug;

        if($student_level ==="sd"){
            $grade = array(1,2,3,4,5,6);
            $semester = array(1,2,3,4,5,6,7,8,9,10,11,12);
        }else if($student_level ==="smp"){
            $grade = array(7,8,9);
            $semester = array(1,2,3,4,5,6);
        }else {
            $grade = array(10,11,12);
            $semester = array(1,2,3,4,5,6);
        }
        $data['grade'] = $grade;
        $data['semester'] = $semester;
        $data['subjects'] = $student->last()->schools->last()->subjects;

        return Response::status('success')->code(200)->result($data);
    }

    public function grade(){
        $user = Auth::user();
        $student = $user->student;

        $school = $student->schools()->first();

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
            default :
                break;
        }

        $result = [
            'grade' => $grade
        ];

        return Response::status('success')->result($result);
    }

    public function semester(Request $request){
        $grade = $request->query('grade');

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
            default :
                $semester = null;
                break;
        }

        $result = [
            'semester' => $semester
        ];

        return Response::status('success')->result($result);
    }

    public function subjects(){
        $user = Auth::user();
        $student = $user->student;

        $school = $student->schools()->first();
        $subjects = $school->subjects()->orderBy('name')->get();

        $result = [];
        foreach ($subjects as $item){
            $result[] = [
                'subject_id' => $item->id,
                'name' => $item->name
            ];
        }

        return Response::status('success')->result($result);
    }

    public function list(Request $request){
        // $dataStudent = Auth::user()->student;
        // $student_id = $dataStudent->id;
        // $student = Student::where('id',$student_id)
        //     ->with(['schools'=>function($q){
        //         $q->with('levels');
        //         $q->with('subjects');
        //     }])
        //     ->get();
        // $student_level = $student->last()->schools->last()->levels->slug;
        // if($student_level ==="sd"){
        //     $grade = array(1,2,3,4,5,6);
        // }else if($student_level ==="smp"){
        //     $grade = array(7,8,9);
        // }else{
        //     $grade = array(10,11,12);
        // }

        $pagination = $request->query('pagination')?$request->query('pagination'):10;
        $semester = $request->query('semester');
        $subject_id = $request->query('subject_id');
        $grade = $request->query('grade');
        $search = $request->query('q');
        $eqSemester = $semester?'=':'!=';
        $eqSubject = $subject_id?'=':'!=';
        $eqGrade = $grade?'=':'!=';

        $learning_material = Lesson::where('semester',$eqSemester,$semester)
            ->where('subject_id',$eqSubject,$subject_id)
            ->where('grade',$eqGrade,$grade)
            ->with(['teacher'=>function($q){
                $q->select('id','name','nip','phone');
            }])
            ->with(['subject'=>function($q){
                $q->select('id','name','code');
            }])
            ->with(['learning_contract_informations'=>function($q){
                $q->select('id','main_topic','sub_topic','category');
            }])
            ->select('id','title','grade','semester','teacher_id','subject_id','learning_contract_information_id');

            if($search != null) {
                $learning_material = $learning_material
                    ->where('title', 'like', '%'.$search.'%')->paginate(10);
            }
            else{
                $learning_material = $learning_material->paginate(10);
            }

            $result = [
                'paginate' => [
                    'total_page' => $learning_material->lastPage(),
                    'total_records' => $learning_material->total(),
                    'current_page' => $learning_material->currentPage()
                ],
                'records' => $learning_material->items()
            ];

        return Response::status('success')->code(200)->result($result);

    }

    public function detail(Request $request){
        $lesson_id = $request->query('lesson_id');

        $learning_material = Lesson::where('id',$lesson_id)
            ->with(['teacher'=>function($q){
                $q->select('id','name','nip','phone');
            }])
            ->with(['subject'=>function($q){
                $q->select('id','name','code');
            }])
            ->with(['learning_contract_informations'=>function($q){
                $q->select('id','main_topic','sub_topic','category');
            }])
            ->with('medias')
            ->select('id','title','description','grade','semester','teacher_id','subject_id','learning_contract_information_id','created_at')
            ->get();

        $data['learning_material']=$learning_material;
        return Response::status('success')->code(200)->result($data);
    }
}
