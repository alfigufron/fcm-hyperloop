<?php

namespace App\Utils;

use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;

use FCM;
use Illuminate\Support\Facades\Log;
use LaravelFCM\Message\Topics;

class FcmSender {

  public static function send($token, $type, $data, $message_notification = null, $send_type = 'default', $topic = null) {
    try {
      $optionBuilder = new OptionsBuilder();
      $optionBuilder->setTimeToLive(60 * 20);
  
      $body = ($type === 'message')
        ? "New Message"
        : (($message_notification) ? $message_notification : "New Notification"); 
  
      $notificationBuilder = new PayloadNotificationBuilder('Hyperloop Edu Pro');
      $notificationBuilder->setBody($body)->setSound('default');
  
      $dataBuilder = new PayloadDataBuilder();
      $dataBuilder->addData([
        'type' => $type,
        'data' => $data
      ]);
    
      $option = $optionBuilder->build();
      $notification = $notificationBuilder->build();
      $data = $dataBuilder->build();
  
    
      if ($send_type === 'default') {
        FCM::sendTo($token, $option, $notification, $data);
      } else if ($send_type === 'topic') {
        $topic_data = new Topics();
        $topic_data->topic($topic);
  
        FCM::sendToTopic($topic_data, $option, $notification, $data);
      }
  
      return;
    } catch (\Exception $err) {
      throw $err;
    }
  }
}