<?php


namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Utils\Paginate;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentWorkController extends Controller
{
    public function list(Request $request){
        $user = Auth::user();
        $student = $user->student;
        $classroom_id = $request->classroom_id;
        $subject_id = $request->subject_id;
        $type = $request->input('type');

        $classroom = Classroom::query()
            ->where(function($typeQuery) use($classroom_id, $subject_id, $student, $type){
                switch ($type){
                    case 'test':
                        $typeQuery
                            ->with(['tests' => function($query) use ($classroom_id, $subject_id, $student) {
                                $query->where('subject_id', $subject_id);
                                $query->where('classroom_id', $classroom_id);
                                $query->with(['student_tests' => function($q) use ($student) {
                                    $q->where('student_id', $student->id);
                                    $q->where('is_allowed', true);
                                }]);
                            }]);
                        break;
                    case 'task':
                        $typeQuery
                            ->with(['assignments' => function($query) use($classroom_id, $subject_id, $student){
                                $query->where('subject_id', $subject_id);
                                $query->where('classroom_id', $classroom_id);
                                $query->with(['student_assignments' => function($q) use ($student) {
                                    $q->where('student_id', $student->id);
                                    $q->where('is_allowed', true);
                                }]);
                            }]);
                        break;
                    default:
                        $typeQuery
                            ->with(['tests' => function($query) use ($classroom_id, $subject_id, $student) {
                                $query->where('subject_id', $subject_id);
                                $query->where('classroom_id', $classroom_id);
                                $query->with(['student_tests' => function($q) use ($student) {
                                    $q->where('student_id', $student->id);
                                    $q->where('is_allowed', true);
                                }]);
                            }])
                            ->with(['assignments' => function($query) use($classroom_id, $subject_id, $student){
                                $query->where('subject_id', $subject_id);
                                $query->where('classroom_id', $classroom_id);
                                $query->with(['student_assignments' => function($q) use ($student) {
                                    $q->where('student_id', $student->id);
                                    $q->where('is_allowed', true);
                                }]);
                            }]);
                        break;
                }
            })
            ->where('id', $classroom_id)->first();

        $assignments = $classroom->assignments;
        $tests = $classroom->assignments;
        $id = 1;
        $records = [];
        foreach ($assignments as $item){
            $dataAssignment = [
                'id' => $id++,
                'type' => 'task',
                'task_id' => $item->id,
                'title' => $item->title,
                'main_topic' => $item->learning_contract_information->main_topic,
                'deadline' => Carbon::parse($item->deadline)->format('d F Y G:i'),
                'filter_date' => Carbon::parse($item->created_at)->toDateString(),
            ];
            array_push($records, $dataAssignment);
        }
        foreach ($tests as $item){
            $dataTest = [
                'id' => $id++,
                'type' => 'test',
                'title' => $item->title,
                'main_topic' => $item->learning_contract_information->main_topic,
                'start_at' => Carbon::parse($item->start_at)->format('d F Y G:i'),
                'filter_date' => Carbon::parse($item->created_at)->toDateString(),
            ];
            array_push($records, $dataTest);
        }

        $final = collect($records)->sortBy('filter_date');
        if ($final == []){
            return Response::status('success')->result("Couldn't find any data");
        }

        $page = Paginate::paginator($final);

        $result = [
            'paginate' => [
                'total_page' => $page->lastPage(),
                'total_records' => $page->total(),
                'current_page' => $page->currentPage()
            ],
            'records' => array_values($page->items())
        ];

        return Response::status('success')->result($result);
    }
}
