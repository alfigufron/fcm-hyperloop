<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotification;
use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use App\Models\RoomChatMember;
use App\Utils\FcmSender;
use App\Utils\Response;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * SEND MESSAGE
     * 
    */
    public function sendMessage(Request $request) {
        $user = Auth::user();

        if ($user->student)
            $detail_user = $user->student;

        if ($user->teacher)
            $detail_user = $user->teacher;

        if ($user->family)
            $detail_user = $user->family;

        // Validation
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'room_chat_id' => 'required|exists:room_chats,id'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        $allowed = RoomChatMember::where('room_chat_id', $request->room_chat_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$allowed)
            return Response::status('failure')->code(404)->result(["Roomchat not found!"]);

        DB::beginTransaction();

        // Store chat & send fcm
        try {
            $chat = new Chat();
            $chat->message = $request->message;
            $chat->room_chat_id = $request->room_chat_id;
            $chat->name = $detail_user->name;
            $chat->sender_id = $user->id;
            $chat->save();

            SendNotification::dispatch($user->fcm_token, 'message', $chat);

            DB::commit();

            return Response::status('success')->code(200)->result(null);
        } catch (\Exception $error) {
            DB::rollBack();

            return Response::status('failure')->code(500)->result($error);
        }
    }

    /**
     * DELETE MESSAGE
     * 
    */
    public function deleteMessage($id) {
        $message = Chat::find($id);
        
        if (!$message)
            return Response::status('failure')->code(404)->result(["Message not found!"]);

        DB::beginTransaction();
        
        // Delete message & send fcm
        try {
            $message->delete();
            
            DB::commit();

            return Response::status('success')->code(200)->result(null);
        } catch (\Exception $error) {
            DB::rollback();;

            return Response::status('failure')->code(500)->result($error);
        }

        return Response::status('success')->code(200)->result(null);
    }
}
