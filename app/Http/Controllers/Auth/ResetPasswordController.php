<?php

namespace App\Http\Controllers\Auth;

use Mail;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Mail\Auth\PasswordReset as PasswordResetMail;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ResetPasswordController extends Controller
{
    /**
     * FORGOT PASSWORD
     * 
     */
    public function forgot(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required',
        ]);

        if($validator->fails()){
            $error = $validator->errors();
            return Helper::response(400, "failure", [], $error);
        }

        $user = User::where('email', $request->email)->first();
        if(!$user){
            $error = ['User not found!'];
            return Helper::response(422, "failure", [], $error);
        }

        $passwordReset = PasswordReset::updateOrCreate([
            'user_id' => $user->id,
        ],[
            'token' => Str::random(40),
        ]);

        // Send Mail
        Mail::to($request->email)->send(new PasswordResetMail($passwordReset->token));
        
        $data = ['email' => $request->email];
        
        return Helper::response(200, "success", $data, []);
    }

    /**
     * RESET PASSWORD
     * 
     */
    public function reset(Request $request){
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'password' => 'required|confirmed',
        ]);

        if($validator->fails()){
            $error = $validator->errors();
            return Helper::response(400, "failure", [], $error);
        }

        $passwordReset = PasswordReset::where('token', $request->token)->first();
        if(!$passwordReset){
            $error = ['Invalid token'];
            return Helper::response(422, "failure", [], $error);
        }

        $user = User::find($passwordReset->user_id);
        $user->password_digest = Hash::make($request->password);
        $user->update();

        $passwordReset->delete();

        return Helper::response(200, "success", [], []);
    }
}
