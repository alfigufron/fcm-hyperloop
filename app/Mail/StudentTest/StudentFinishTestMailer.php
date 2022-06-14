<?php

namespace App\Mail\StudentTest;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentFinishTestMailer extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from("lumenizure@gmail.com")
            ->subject($this->data->student->name.' Has Cleared Test')
            ->view('mail.test.student-finish-test', [
                'data' => $this->data
            ]);
    }
}
