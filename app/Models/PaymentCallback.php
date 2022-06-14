<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCallback extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'payment_callbacks';
    
    protected $fillable = [
        'transaction_id', 'order_id', 'response', 'status', 'paymentable_id', 'paymentable_type',
    ];
}
