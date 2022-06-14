<?php


namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Utils\Paginate;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TeacherForumWorkControllers extends Controller
{
    public function list(Request $request){
        $classroomId = $request->input('classroom_id');
        $subjectId = $request->input('subject_id');
        $type = $request->input('type');
        $done = $request->input('done');

        $mainQuery = Classroom::query()
            ->where('id', $classroomId)
            ->with(['assignments' => function($query) use ($done, $classroomId, $subjectId) {
                $query->where('subject_id', $subjectId);
                $query->where('classroom_id', $classroomId);
                if ($done == 'false'){
                    $query->whereHas('student_assignments', function ($q){
                        $q->where('score', null);
                    });
                }
                else{
                    $query->whereHas('student_assignments', function ($q){
                        $q->where('score', '!=', null);
                    });
                }
            }])
            ->with(['tests' => function($query) use ($done, $subjectId, $classroomId){
                $query->where('subject_id', $subjectId);
                $query->where('classroom_id', $classroomId);
                if ($done == 'false'){
                    $query->whereHas('student_tests', function ($q){
                        $q->where('score', null);
                    });
                }
                else{
                    $query->whereHas('student_tests', function ($q){
                        $q->where('score', '!=', null);
                    });
                }
            }])->first();


        // Task
        $i = 1;
        $tasks = $mainQuery->assignments;
        $records = [];
        foreach ($tasks as $task){
            $dataTask = [
                'id' => $i++,
                'type' => 'assigment',
                'task_id' => $task->id,
                'title' => $task->title,
                'main_topic' => $task->learning_contract_information->main_topic,
                'filter_date_time' => Carbon::parse($task->created_at)->toDateTimeString(),
            ];
            array_push($records, $dataTask);
        }

        $tests = $mainQuery->tests;
        foreach ($tests as $test){
            $dataTest = [
                'id' => $i++,
                'type' => 'test',
                'test_id' => $test->id,
                'title' => $test->title,
                'main_topic' => $test->learning_contract_information->main_topic,
                'filter_date_time' => Carbon::parse($test->created_at)->toDateTimeString(),
            ];
            array_push($records, $dataTest);
        }

        $final = collect($records)->sortByDesc('filter_date_time');
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
