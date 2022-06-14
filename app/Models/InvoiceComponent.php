<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceComponent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'price',
    ];

    public function studentinvoices(){
        return $this->belongsToMany('App\Models\StudentInvoice', 'student_invoice_payments')
            ->using('App\Models\StudentInvoiceComponent');
    }
}
