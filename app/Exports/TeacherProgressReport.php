<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;


class TeacherProgressReport implements FromView
{
    public function __construct(int $teacher_id) 
    {
        $this->teacher_id = $teacher_id;
    }
    
    public function view(): View
    {
        $user = Auth::user();
        $teacher = $user->teacher;
        $schedules = Schedule::where("teacher_id",$this->teacher_id)
            ->whereNotNull('learning_contract_information_id')
            ->with('classroom')
            ->with('subject')
            ->with('learning_contract_information')
            ->groupBy('date','schedules.id')
            ->get();
        
        $data['teacher'] = $teacher->name;
        $data['schedules'] = $schedules;

        return view('exports.TeacherProgressReport',$data);
    }
}