<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Utils\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentOffenceController extends Controller {
    public function list(Request $request) {
        $student = Auth::user()->student;
        $dashboard = $request->input('dashboard');

        switch ($dashboard){
            case 'true':
                $dash = true;
                break;
            case 'false':
                $dash = false;
                break;
            default:
                break;
        }

        $rawQuery = Student::query()
            ->with('offences')
            ->where('id', $student->id)
            ->first();
        $offence = $rawQuery->offences;

        $result = [];
        $i = 0;
        if ($dash){
            foreach ($offence as $item){
                $dataResult = [
                    'name' => $item->penalty
                ];
                array_push($result, $dataResult);
                if (++$i == 5) break;
            }
        }
        else{
            foreach ($offence as $item){
                $dataResult = [
                    'id' => $item->id,
                    'name' => $item->penalty,
                    'description' => $item->description
                ];
                array_push($result, $dataResult);
            }
        }

        return Response::status('success')->result($result);
    }

    public function detail(Request $request, $id) {
        $student = Auth::user()->student;

        $penalty = $student->penalties->find($id)->makeHidden('file');

        return Response::status('success')->code(200)->result($penalty);
    }
}
