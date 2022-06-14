<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use App\Models\Assignment;



class TeacherAssignmentStudent implements FromView
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
                $q->with(['students'=>function($q){
                    $q->with(['schools'=>function($q){
                        $q->with('school_minimum_competency','subjects');
                    }]);
                }]);
            }])
            ->get();
            $data['assignment'] = $assignment;
        return view('exports.TeacherAssignmentReport',$data);
    }
}