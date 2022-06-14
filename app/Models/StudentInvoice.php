<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentInvoice extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code', 'invoice_type', 'total', 'status', 'due_date',
    ];

    public function invoice_components(){
        return $this->belongsToMany('App\Models\InvoiceComponent', 'student_invoice_components')
            ->using('App\Models\StudentInvoiceComponent');
    }

    public function student_invoice_payments(){
        return $this->hasMany('App\Models\StudentInvoicePayment');
    }

    public function student(){
        return $this->belongsTo('App\Models\Student');
    }

    public function payment_callback(){
        return $this->hasMany('App\Models\PaymentCallback', 'paymentable_id');
    }
}
