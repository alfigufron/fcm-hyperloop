<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use App\Models\Assignment;



class StudentRemedialReport implements FromView
{
    public function __construct(int $assignment_id) 
    {
        $this->assignment_id = $assignment_id;
    }
    
    public function view(): View
    {
        $assignment = Assignment::where('id', $this->assignment_id)
            ->with('classroom')
            ->with('subject')
            ->with(['student_assignments'=>function($q){
                $q->where('is_remedial',true);
                $q->with('students');
                $q->with('student_assignment_attempts');

            }])
            ->get();
            $data['assignment'] = $assignment;
        return view('exports.StudentRemedial',$data);
    }
}