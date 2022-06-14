<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Utils\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function all() {
        $user = Auth::user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('id', 'DESC')
            ->get();

        return Response::status('success')->result($notifications);
    }
}
