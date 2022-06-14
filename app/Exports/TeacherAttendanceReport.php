<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use App\Models\TeacherAttendance;


class TeacherAttendanceReport implements FromView
{
    public function __construct(int $teacher_id) 
    {
        $this->teacher_id = $teacher_id;
    }
    
    public function view(): View
    {
        $attendance = TeacherAttendance::where('teacher_id',$this->teacher_id)
            ->with(['schedule'=>function($q){
                $q->with('classroom','subject');
            }])
            ->with('teacher')
            ->get();
        $data['attendance'] = $attendance;

        return view('exports.TeacherAttendanceReport',$data);
    }
}