<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class FcmController extends Controller
{
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        $user = Auth::user();

        $user = User::find($user->id);
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return Response::status('success')->result(null);
    }
}
