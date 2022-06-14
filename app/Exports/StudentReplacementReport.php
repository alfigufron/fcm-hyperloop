<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use App\Models\Schedule;


class StudentReplacementReport implements FromView
{
    public function __construct(int $schedule_id) 
    {
        $this->schedule_id = $schedule_id;
    }
    
    public function view(): View
    {
        $schedule = Schedule::where('id', $this->schedule_id)
        ->with('classroom','subject','teacher')
        ->with(['replacements'=>function($q){
             $q->with('students');
        }])
        ->get();
        $data['schedules'] = $schedule;
        return view('exports.StudentReplacement',$data);
    }
}