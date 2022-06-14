<?php

namespace App\Http\Controllers\Auth;

use JWTAuth;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;

class LoginController extends Controller
{

    public function login(Request $request)
    {
        $login = $request->input('user');

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $request->merge([$field => $login]);

        $credentials = $request->only($field, 'password');

        $user = User::where($field, $request->user)->first();

        if($user == null){
            return Helper::response(422, "failure", [], ["Couldn't find user"]);
        }

        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return Helper::response(400, "failure", [], ['Invalid credentials']);
            }
        } catch (JWTException $e) {
            return Helper::response(500, "failure", [], ["Couldn't create token"]);
        }

        $studentFamilies = [];
        switch($user->role->slug){
            case 'parents':
                $body = $user->family;
                $studentFamilies = $body->student_families;
                $identity = $body->identity_number;
                $school = null;
                $profilePicture = $body->profile_picture;
                $address = $body->address;
                $number = $body->phone;
                $grade = null;
                break;
            case 'teacher':
                $body = $user->teacher;
                $identity = $body->nip;
                if ($body->schools()->exists()){
                    $school = $body->schools()->first()->name;
                }
                elseif ($body->dormitories()->exists()){
                    $first = $body->dormitories()->first();
                    $school = $first->schools->name;
                }
                else{
                    $school = "";
                }
                $profilePicture = $body->profile_picture;
                $address = $body->address;
                $number = null;
                $grade = null;
                break;
            case 'student':
                $body = $user->student;
                $identity = $body->nis;
                $school = $body->schools()->first()->name;
                $profilePicture = $body->student_detail->profile_picture;
                $address = $body->student_detail->address;
                $number = $body->student_detail->phone;
                $grade = $body->classrooms()->wherePivot('is_active', true)->first()->grade;
                break;
            case "admin-admission":
            case "admin-finance":
            case 'admin-school':
                $body = $user->school_admin;
                $identity = null;
                $school = $body->school->name;
                $profilePicture = $body->profile_picture;
                $address = $body->address;
                $number = $body->phone;
                $grade = null;
            break;
            case "admin-dormitory":
                $body = $user->dormitory_admin;
                $identity = null;
                $school = $body->dormitory->schools->name;
                $profilePicture = $body->profile_picture;
                $address = $body->address;
                $number = $body->phone;
                $grade = null;
                break;
            default:
                break;
        }
        $user->api_token = $token;
        $user->update();

        $explodedName = explode(' ', $body->name);
        $firstName = $explodedName[0];

        $data = [
            'id' => $user->id,
            'token'=> $token,
            'role' => $user->role->slug,
            'name'=> $body->name,
            'first_name' => $firstName,
            'email'=> $user->email,
            'address' => $address,
            'phone_number' => $number,
            'profile_picture' => $profilePicture,
            'identity_number'=> $identity,
            'school' => $school,
            'grade' => $grade,
            'is_active'=> ''
        ];

        if($user->role->slug == 'parents'){
            $data['has_many_student'] = count($studentFamilies) > 1 ? TRUE : FALSE;
        }

        return Helper::response(200, "success", $data, []);
    }

    public function register(Request $request){
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role_id' => 'required',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }

        $user = User::create([
            'username' => $request->get('username'),
            'email' => $request->get('email'),
            'password_digest' => Hash::make($request->get('password')),
            'role_id' => $request->get('role_id'),
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json(compact('user','token'),201);
    }

}
