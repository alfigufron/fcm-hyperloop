<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideoMeetParticipantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('video_meet_participants', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('video_meet_id');
            $table->bigInteger('user_id');
            $table->datetime('start_at');
            $table->datetime('finish_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('video_meet_participants');
    }
}
