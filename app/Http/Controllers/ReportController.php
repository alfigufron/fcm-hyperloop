<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utils\Response;
use App\Exports\TeacherAssignmentStudent;
use App\Exports\StudentRemedialReport;
use App\Exports\StudentReplacementReport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function TeacherAssignmentToStudent($assignment_id){
        return Excel::download(new TeacherAssignmentStudent($assignment_id), 'Teacher Assignment to Student Report.xlsx');  
    }

    public function StudentReplacement($schedule_id){
        return Excel::download(new StudentReplacementReport($schedule_id), 'Student Replacement Report.xlsx');  
        $schedule = Schedule::where('id', $schedule_id)
            ->with('classroom','subject','teacher')
            ->with(['replacements'=>function($q){
                 $q->with('students');
            }])
            ->get();
        $data['schedules'] = $schedule;
        return view('exports.StudentReplacement',$data);
    }

    public function StudentRemedial($assignment_id){
        return Excel::download(new StudentRemedialReport($assignment_id), 'Student Remedial Report.xlsx');  
    }
}
