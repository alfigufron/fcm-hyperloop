<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Utils\Response;
use App\Exports\StudentAttendanceReport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Schedule;

class StudentAttendanceReportController extends Controller
{
    public function index($id){
        return Excel::download(new StudentAttendanceReport($id), 'Student Attendance Report.xlsx');  
    }
}
