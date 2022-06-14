<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Utils\Response;

use App\Models\RoomChat;
use App\Models\RoomChatMember;

class RoomchatController extends Controller
{
    public function list() {
        $user = Auth::user();
        $roomchats = RoomChatMember::where('user_id', $user->id)
            ->with('roomchat')
            ->get();

        return Response::status('success')->code(200)->result($roomchats);
    }

    /**
     * OPEN
     * 
     */
    public function open(Request $request, $id) {
        $user = Auth::user();
        $limit = ($request->limit && $request->limit >= 10)
            ? $request->limit
            : 10;

        $roomchat = RoomChat::with('roomchat_member')->find($id);

        // Validation
        if (!$roomchat)
            return Response::status('failure')->code(404)->result(["Roomchat not found!"]);

        $allowed = RoomChatMember::where('room_chat_id', $roomchat->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$allowed)
            return Response::status('failure')->code(404)->result(["Roomchat not found!"]);

        // Get chats
        $roomchat['chats'] = Chat::where('room_chat_id', $roomchat->id)
            ->limit($limit)
            ->latest()
            ->get();

        return Response::status('success')->code(200)->result($roomchat);
    }

    /**
     * CREATE
     * 
     */
    public function create(Request $request) {
        $user = Auth::user();

        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'string|nullable',
            'type' => 'required|in:personal,group',
            'members' => 'required|array',
            'members.*' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        if ($request->type === 'group' && $user->role->slug !== 'teacher')
            return Response::status('failure')
                ->code(422)
                ->result("you don't have access to create group");

        $members = $request->members;
        array_push($members, $user->id);

        DB::beginTransaction();

        try {
            // Create roomchat
            $roomchat = new RoomChat();
            $roomchat->name = $request->name;
            $roomchat->room_type = $request->room_type;
            $roomchat->save();

            // Add member roomchat
            foreach ($members as $member_id) {
                $roomchat_member = new RoomChatMember();
                $roomchat_member->user_id = intval($member_id);
                $roomchat_member->is_admin = 
                    (intval($member_id) === $user->id)
                        ? true
                        : false;
                $roomchat_member->room_chat_id = $roomchat->id;
                $roomchat_member->save();
            }

            DB::commit();

            return Response::status('success')->code(200)->result(null);
        } catch (\Exception $error) {
            DB::rollBack();

            return Response::status('failure')->code(500)->result($error);
        }
    }

    /**
     * DELETE
     * 
     */
    public function delete($id) {
        $roomchat = RoomChat::find($id);

        if (!$roomchat)
            return Response::status('failure')->code(404)->result(["Roomchat not found!"]);
        
        $roomchat->delete();
        
        return Response::status('success')->code(200)->result(null);
    }

    /**
     * Classroom List
     * 
     */
    public function classroomList() {
        $user = Auth::user();
        $teacher = $user->teacher;
        
        $classrooms = $teacher->teacher_classrooms->where('classroom_type', 'regular');

        if (!$classrooms)
            return Response::status('success')->code(200)->result([]);

        $result = [];
        foreach ($classrooms as $classroom) {
            $data = [
                'id' => $classroom->id,
                'code' => $classroom->code,
                'name' => $classroom->name,
                'grade' => $classroom->grade
            ];

            array_push($result, $data);
        }

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * Member List
     * 
     */
    public function memberList(Request $request) {
        $validator = Validator::make($request->all(), [
            'classroom_id' => 'required|exists:classrooms,id'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        $classroom = Classroom::find($request->classroom_id);
        $student_classrooms = $classroom->students;

        $result = [];
        foreach ($student_classrooms as $student_key => $student) {
            $result[$student_key] = [
                'id' => $student->user_id,
                'name' => $student->name
            ];

            $student_families = $student->student_families
                ->where('relationship_role', 'parents');

            foreach ($student_families as $student_family) {
                $family = $student_family->family;

                if ($family->user_id)
                    $result[$student_key]['parent'] = [
                        'id' => $family->user_id,
                        'name' => $family->name
                    ];
                
            }
        }
        
        return Response::status('success')->code(200)->result($result);
    }
}
