<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utils\Response;
use App\Exports\TeacherAttendanceReport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\TeacherAttendance;

class TeacherAttendanceReportController extends Controller
{
    public function index($teacher_id){
        return Excel::download(new TeacherAttendanceReport($teacher_id), 'Teacher Attendance Report.xlsx');  
    }
}
