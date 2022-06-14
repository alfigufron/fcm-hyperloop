<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utils\Response;
use App\Exports\TeacherProgressReport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeacherProgressReportController extends Controller
{
    public function index($teacher_id){
        return Excel::download(new TeacherProgressReport($teacher_id), 'Teacher Progress Report.xlsx');  
        $user = Auth::user();
        $teacher = $user->teacher;
        $schedules = Schedule::where("teacher_id",$teacher->id)
        ->whereNotNull('learning_contract_information_id')
        ->with('classroom')
        ->with('subject')
        ->with('learning_contract_information')
        // ->select("")
        // ->groupBy('date')
        ->get();

        return $schedules;
        
        $data['teacher'] = $teacher->name;
        $data['schedules'] = $schedules;

        return view('exports.TeacherProgressReport',$data);
    }
}