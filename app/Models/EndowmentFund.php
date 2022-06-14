<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EndowmentFund extends Model
{
    protected $table = 'endowment_funds';

    protected $fillable = ['transaction_id', 'order_id', 'response', 'status', 'paymentable_id', 'paymentable_type', 'payment_type'];

    /**
     * USER
     * 
     */
    public function user(){
        return $this->belongsTo('App\Models\User');
    }
}
