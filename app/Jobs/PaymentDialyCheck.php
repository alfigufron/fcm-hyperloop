<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\StudentFamily;
use App\Models\StudentInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use App\Utils\FcmSender;
use Carbon\Carbon;

class PaymentDialyCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("Job Payment Dialy Check Start");

        $due_date_invoices = [];
        $before_due_invoices = [];

        $student_invoices = StudentInvoice::whereIn('status', ['unpaid', 'half_paid'])->get();
        
        foreach ($student_invoices as $data) {
            $student = $data->student;
            $parent = StudentFamily::with('family')
                ->where([
                    ['relationship_role', 'parents'],
                    ['student_id', $student->id]
                ])->get()[0]->family;
            $due_date = new Carbon($data->due_date);
            $date_now = Carbon::now();

            $diff_before = $due_date->diffInDays($date_now, false);
            $diff_after = $date_now->diffInDays($due_date, false);

            if ($diff_before >= 0 && $diff_before <= 7) {
                if (!in_array($parent->user_id, $before_due_invoices))
                    array_push($before_due_invoices, $parent->user_id);
            } else if ($diff_after < 0) {
                if (!in_array($parent->user_id, $due_date_invoices))
                    array_push($due_date_invoices, $parent->user_id);
            }
        }

        foreach ($due_date_invoices as $user_id) {
            $notifications = Notification::where('user_id', $user_id)
                ->orderBy('id', 'ASC')
                ->get();
            
            if (count($notifications) >= 10)
                $notifications[0]->delete();

            Notification::create([
                'notification' => "You have bills that are past due",
                'user_id' => $user_id
            ]);
        }

        foreach ($before_due_invoices as $user_id) {
            $notifications = Notification::where('user_id', $user_id)
                ->orderBy('id', 'ASC')
                ->get();
            
            if (count($notifications) >= 10)
                $notifications[0]->delete();

            Notification::create([
                'notification' => "You have a bill that is almost due",
                'user_id' => $user_id
            ]);
        }
        
        FCMSender::send(null, 'notification', $due_date_invoices, "You have bills that are past due", 'topic', 'payment-dialy-notification');
        FCMSender::send(null, 'notification', $before_due_invoices, "You have a bill that is almost due", 'topic', 'payment-dialy-notification');

        Log::info("Job Payment Dialy Check Done");
    }
}
