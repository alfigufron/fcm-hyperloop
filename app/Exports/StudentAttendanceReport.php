<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use App\Models\Schedule;


class StudentAttendanceReport implements FromView
{
    public function __construct(int $schedule_id) 
    {
        $this->schedule_id = $schedule_id;
    }
    
    public function view(): View
    {
        $schedules = Schedule::where('id',$this->schedule_id) 
        ->with('classroom')
        ->with('teacher')
        ->with('subject')
        ->with(['schedule_attendances'=>function($q){
            $q->with('student');
        }])
        ->get();
        $data['schedules'] = $schedules;

        
        return view('exports.StudentAttendanceReport',$data);
    }
}