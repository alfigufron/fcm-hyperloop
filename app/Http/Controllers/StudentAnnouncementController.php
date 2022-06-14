<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Utils\Response;

use App\Models\Dormitory;
use App\Models\School;

class StudentAnnouncementController extends Controller {
    public function list(Request $request) {
        $student = Auth::user()->student;

        if (count($student->schools)) {
            $school_id = $student->schools->first()->id;

            $agendas = School::find($school_id)->agendas;
        }

        if (count($student->dormitories)) {
            $dormitory_id = $student->schools->first()->id;

            $agendas = Dormitory::find($dormitory_id)->agendas;
        }

        if ($request->dashboard)
            $agendas = $agendas->take(5);
        
        return Response::status('success')->code(200)->result($agendas);
    }
}
