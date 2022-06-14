<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentInvoicePayment;
use App\Utils\Price;
use App\Utils\Response;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ParentPaymentController extends Controller
{
    /**
     * PARENT PAYMENT SCHOOL LIST
     * 
     */
    public function list(Request $request){
        $studentId = $request->query('student_id');

        $studentInvoices = StudentInvoice::where('student_id', $studentId)->whereIn('status', ['unpaid', 'half_paid'])
        ->with('student_invoice_payments')->orderBy('created_at', 'DESC')->paginate(10);

        $records = [];
        foreach($studentInvoices->items() as $invoice){
            // Counting payment
            $paid = 0;
            foreach($invoice->student_invoice_payments as $payment){
                $paid = $paid + $payment->amount;
            }

            // Status
            $formattedStatus = null;
            switch($invoice->status){
                case 'paid':
                    $formattedStatus = "Paid";
                    break;
                case 'half_paid':
                    $formattedStatus = "Half paid";
                    break;
                case 'unpaid':
                    $formattedStatus = "Not yet paid";
                    break;
                default:
                    break;
            }

            $dataPush = [
                'student_invoice_id' => $invoice->id,
                'code' => $invoice->code,
                'due_date' => Carbon::parse($invoice->due_date)->format('d F Y'),
                'status' => [
                    'raw' => $invoice->status,
                    'formatted' => $formattedStatus
                ],
                'cost' => [
                    'total' => Price::formatted($invoice->total),
                    'paid' => Price::formatted($paid),
                    'unpaid' => Price::formatted($invoice->total - $paid)
                ]
            ];

            array_push($records, $dataPush);
        }

        $pagination = [
            'total_page' => $studentInvoices->lastPage(),
            'total_records' => $studentInvoices->total(),
            'current_page' => $studentInvoices->currentPage()
        ];

        $result = [
            'pagination' => $pagination,
            'records' => $records
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * PARENT PAYMENT SCHOOL PAYMENT GATEWAY
     * 
     */
    public function paymentGateway(Request $request){
        $invoiceId = $request->student_invoice_id;
        $amount = $request->amount;

        $studentInvoice = StudentInvoice::where('id', $invoiceId)->with('student.student_detail')->first();

        if(!$studentInvoice){
            return Response::status('failure')->code(404)->result(["Data not found!"]);
        }

        try {
            $client = new Client();
            $midtransUrl = env('MIDTRANS_SANDBOX_URL');
            $midtransServerKey = env('MIDTRANS_SANDBOX_SERVER_KEY');

            $randString = strtoupper(Str::random(3));
            $orderId = "SI-AT-".strtotime(date('Y-m-d H:i:s')).$randString;
            
            $response = $client->request('POST', $midtransUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic '.base64_encode($midtransServerKey.':')
                ],
                'json' => [
                    "transaction_details" => [
                        "order_id" => $orderId,
                        "gross_amount" => $amount
                    ],
                    "customer_details" => [
                        "first_name" => $studentInvoice->student->name,
                        "email" => $studentInvoice->student->email,
                        "phone" => $studentInvoice->student->student_detail->phone
                    ],
                    "enabled_payments" => [
                        "bca_va", "bni_va", "echannel",
                    ],
                    "expiry" => [
                        "unit" => "days",
                        "duration" => 1
                    ]
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents());

            return Response::status('success')->code($response->getStatusCode())->result($result);
        } catch (RequestException $e) {
            return Response::status('failure')->code(422)->result(["Transaction invalid"]);
        }
    }

    /**
     * Home Payment Data
     * 
     */
    public function home(Request $request) {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        $invoices = Student::find($request->student_id)->invoices->count();

        $payment = ($invoices === 1)
            ? 'payment'
            : 'payments';

        $result = [
            'payment_amount' => "2 $payment not yet paid"
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * List Payment (Unpaid)
     * 
     */
    public function unpaid(Request $request) {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        $invoices = StudentInvoice::with(
            'invoice_components',
            'student_invoice_payments'
        )
            ->where('student_id', $request->student_id)
            ->where('status', '!=', 'paid_off')
            ->get();

        $result = [];
        $key = 0;
        foreach ($invoices as $invoice) {
            $invoice_components = [];
            $status = "";

            foreach ($invoice->student_invoice_payments as $student_invoice_payment)
                if ($student_invoice_payment->status === 'pending')
                    $status = 'pending';

            if ($status !== 'pending') {
                foreach ($invoice->invoice_components as $component_key => $invoice_component)
                    $invoice_components[$component_key] = [
                        'title' => $invoice_component->title,
                        'price' => $invoice_component->price,
                    ];

                $invoice_payment = [];
                $half_amount = 0;
                if ($invoice->status === 'half_paid') {

                    foreach ($invoice->student_invoice_payments as $payment_key => $student_invoice_payment)
                        if ($student_invoice_payment->status === 'approved') {
                            $half_amount = $half_amount + $student_invoice_payment->amount;

                            $invoice_payment[$payment_key] = [
                                'amount' => $student_invoice_payment->amount,
                                'date' => $student_invoice_payment->date
                            ];
                        }
                }
    
                $result[$key] = [
                    'code' => $invoice->code,
                    'status' => $invoice->status,
                    'due_date' => $invoice->due_date,
                    'total' => $invoice->total - $half_amount,
                    'invoice_components' => $invoice_components,
                    'student_invoice_payments' => $invoice_payment
                ];

                $key++;
            }
        }

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * List Payment (Pending)
     * 
     */
    public function pending(Request $request) {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        $invoices = StudentInvoice::with(
            'invoice_components',
            'student_invoice_payments'
        )
            ->where('student_id', $request->student_id)
            ->whereHas('student_invoice_payments', function ($query) {
                return $query->where('status', 'pending');
            })
            ->get();

        return Response::status('success')->code(200)->result($invoices);
    }

    /**
     * List Payment (Finish)
     * 
     */
    public function finish(Request $request) {
        $filter = [];

        if ($request->status)
            $filter['status'] = $request->status;

        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }

        $invoices = ($request->status)
            ? StudentInvoicePayment::with('studentinvoices')
                ->where($filter)
                ->get()
            : StudentInvoicePayment::with('studentinvoices')
                ->where('status', 'rejected')
                ->orWhere('status', 'approved')
                ->get();
        
        return $invoices;
    }

    /**
     * Show Detail Payment
     * 
     */
    public function detailPayment($id) {
        $invoice = StudentInvoice::with('student')->find($id);

        if (!$invoice)
            return Response::status('failure')->code(422)->result(["Invoice Not Found"]);

        $result = [
            'code' => $invoice->code,
            'student_name' => $invoice->student->name,
            'student_nis' => $invoice->student->nis,
            'total' => $invoice->total
        ];

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * Confirm Payment
     * 
     */
    public function confirmPayment(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer',
            'file' => 'required|mimes:jpg,jpeg,jfif,heic,png,doc,docx,pdf'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $error = [];
            foreach ($errors->all() as $message)
                array_push($error, $message);

            return Response::status('failure')->code(422)->result($error);
        }
        
        $invoice = StudentInvoice::with('student')->find($id);

        if (!$invoice)
            return Response::status('failure')->code(422)->result(["Invoice Not Found"]);
            
        $exist = StudentInvoicePayment::where('status', 'pending')
            ->where('student_invoice_id', $id)
            ->first();

        if ($exist)
            return Response::status('failure')->code(422)->result(["Student Invoice Payment is being processed"]);

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $filename = Str::uuid().'_'.time().'.'.$file->getClientOriginalExtension();
            $path_file = 'invoices/';

            $file->storeAs($path_file, $filename, 'gcs');
            $disk = Storage::disk('gcs');
            $path = $disk->url($path_file.$filename);

            $student_invoice_payment = StudentInvoicePayment::create([
                'student_invoice_id' => $id,
                'date' => Carbon::now(),
                'amount' => $request->amount,
                'payment_type' => 'automatic',
                'status' => 'pending',
                'file' => $path
            ]);

            DB::commit();

            return Response::status('success')->code(200)->result($student_invoice_payment);
        } catch (\Exception $err) {
            DB::rollBack();
            return Response::status('failure')->code(500)->result($err);
        }
    }
}
