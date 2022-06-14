<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentInvoicePayment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'amount', 'date', 'student_invoice_id', 'payment_type',
        'status', 'file'
    ];

    public function studentinvoices() {
        return $this->belongsTo('App\Models\StudentInvoice', 'student_invoice_id');
    }
}
