<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use App\Utils\FcmSender;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $token;

    protected $type;

    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        $token,
        $type,
        $data,
        $message_notification = null,
        $send_type = 'default',
        $topic = null
    ) {
        $this->token = $token;
        $this->type = $type;
        $this->data = $data;
        $this->message_notification = $message_notification;
        $this->send_type = $send_type;
        $this->topic = $topic;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("Job Notification Start");
        
        FCMSender::send(
            $this->token,
            $this->type,
            $this->data,
            $this->message_notification,
            $this->send_type,
            $this->topic,
        );

        Log::info("Job Notification Done");
    }
}
