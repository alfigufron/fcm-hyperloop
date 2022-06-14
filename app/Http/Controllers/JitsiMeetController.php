<?php

namespace App\Http\Controllers;

use App\Models\ScheduleAttendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\VideoMeet;
use App\Models\VideoMeetParticipant;
use App\Utils\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;

class JitsiMeetController extends Controller
{
    /**
     * HANG UP
     * 
     */
    public function hangUp(Request $request){
        $displayName = $request->display_name;

        $explodedDisplayName = explode(" # ", $displayName);
        if(count($explodedDisplayName) != 2){
            return Response::status('failure')->code(422)->result(["Display name is invalid. Failed to check attendance!"]);
        }

        // Find the data
        $identityNumber = $explodedDisplayName[1];
        $videoMeetParticipant = VideoMeetParticipant::whereHas('user', function($q) use($identityNumber){
            $q->whereHas('student', function($q) use($identityNumber){
                $q->where('nis', $identityNumber);
            });
            $q->orWhereHas('teacher', function($q) use($identityNumber){
                $q->where('nip', $identityNumber);
            });
        })->with('video_meet.schedule')->orderBy('created_at', 'DESC')->first();

        if(!$videoMeetParticipant){
            return Response::status('failure')->code(422)->result(["Participant is not found!"]);
        }

        $schedule   = $videoMeetParticipant->video_meet->schedule; 
        $student    = $videoMeetParticipant->user->student;
        $teacher    = $videoMeetParticipant->user->teacher;
        $now        = Carbon::now();

        // Finish the meeting
        if($teacher){
            $videoMeet = VideoMeet::find($videoMeetParticipant->video_meet_id);
            $videoMeet->finish_at = $now;
            $videoMeet->save();
        }

        // Schedule attendance update
        if($student){
            $status = "absent";
            if($videoMeetParticipant->video_meet->finish_at){
                $status = "present";
            }
            
            ScheduleAttendance::updateOrCreate([
                'schedule_id' => $schedule->id,
                'student_id' => $student->id
            ],[
                'status' => $status
            ]);
        }

        // Update finish_at
        $videoMeetParticipant->finish_at = $now;
        $videoMeetParticipant->save();

        return Response::status('success')->code(200)->result([]);
    }
}
