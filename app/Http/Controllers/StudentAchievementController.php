<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Utils\Response;

class StudentAchievementController extends Controller {
    public function list(Request $request) {
        $student = Auth::user()->student;
        $dashboard = $request->input('dashboard');

        switch ($dashboard) {
            case 'true':
                $dash = true;
                break;
            case 'false':
                $dash = false;
                break;
            default:
                break;
        }

        $achievements = $student->achievement()->get();
        $record = [];
        if ($dash)
        foreach ($achievements as $achievement){
            $result = [
                'name' => $achievement->name
            ];
            array_push($record, $result);
        }
        else{
            foreach ($achievements as $achievement){
                switch ($achievement->pivot->is_pillar){
                    case true:
                        $leader = 'yes';
                        break;
                    case false :
                        $leader = 'no';
                        break;
                    default:
                        break;
                }

                $result = [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'predicate' => $achievement->predicate,
                    'leader' => $leader
                ];
                array_push($record, $result);
            }
        }

        return Response::status('success')->code(200)->result($record);
    }

    public function detail($id) {
        $student = Auth::user()->student;
        $achievement = $student->achievement
            ->find($id)
            ->only(['name', 'description', 'date' , 'predicate']);

        $achievement['is_pillar'] = $student->achievement->find($id)->pivot->is_pillar;

        return Response::status('success')->code(200)->result($achievement);
    }
}
