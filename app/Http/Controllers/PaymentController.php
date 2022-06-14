<?php

namespace App\Http\Controllers;

use App\Models\EndowmentFund;
use App\Models\PaymentCallback;
use App\Models\PaymentMethod;
use App\Models\StudentInvoice;
use App\Utils\Response;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Validator;

class PaymentController extends Controller
{
    /**
     * METHODS
     * 
     */
    public function methods(){
        $result = PaymentMethod::where('is_active', TRUE)->orderBy('payment_type')->get();

        return Response::status('success')->code(200)->result($result);
    }

    /**
     * PAY
     * 
     */
    public function pay(Request $request){
        // Validator
        $validator = Validator::make($request->all(), [
            'type' => [
                'required',
                Rule::in(['endowment', 'student_invoices'])
            ],
            'invoice_id' => 'required',
            'gross_amount' => 'required',
            'bank' => 'required'
        ]);

        if($validator->fails()){
            return Response::status('failure')->code(401)->result($validator->errors());
        }

        // Init data request
        $type           = $request->type;
        $invoiceId      = $request->invoice_id;
        $grossAmount    = $request->gross_amount;
        $bank           = $request->bank;

        /**
         * Check type
         * 
         */
        $customerDetails = [];
        switch($type){
            case 'endowment_funds':
                $endowment = EndowmentFund::where('id', $invoiceId)->with('user.role')->first();
                if(!$endowment){
                    return Response::status('failure')->code(404)->result(["Data not found!"]);
                }

                $firstName  = null;
                $email      = null;
                $user       = $endowment->user;
                switch($user->role->slug){
                    case 'admin-admission':
                    case 'admin-finance':
                    case 'admin-school':
                        $firstName = $user->school_admin->name;
                        $email = $user->email;
                        break;
                    case 'admin-dormitory':
                        $firstName = $user->dormitory_admin->name;
                        $email = $user->email;
                        break;
                    case 'advicer':
                    case 'teacher':
                        $firstName = $user->teacher->name;
                        $email = $user->email;
                        break;
                    case 'parents':
                        $firstName = $user->family->name;
                        $email = $user->email;
                        break;
                    case 'student':
                        $firstName = $user->student->name;
                        $email = $user->email;
                        break;
                }

                $orderId = "EF-AT-".strtotime(date('Y-m-d H:i:s')).strtoupper(Str::random(3));
                $customerDetails = [
                    'first_name' => $firstName,
                    'email' => $email
                ];
                break;
            case 'student_invoices':
                $studentInvoice = StudentInvoice::where('id', $invoiceId)->with('student.student_detail')->first();
                if(!$studentInvoice){
                    return Response::status('failure')->code(404)->result(["Data not found!"]);
                }

                $orderId = "SI-AT-".strtotime(date('Y-m-d H:i:s')).strtoupper(Str::random(3));
                $customerDetails = [
                    'first_name' => $studentInvoice->student->name,
                    'email' => $studentInvoice->student->student_detail->email,
                    'phone' => $studentInvoice->student->student_detail->phone
                ];
                break;
            default:
                break;
        }

        /**
         * Midtrans request body
         * 
         */
        $midtransRequestBody = [
            "transaction_details" => [
                "order_id" => $orderId,
                "gross_amount" => $grossAmount
            ],
            "customer_details" => $customerDetails
        ];

        /**
         * Bank type
         * 
         */
        switch($bank){
            case 'bca':
            case 'bni':
            case 'bri':
                $midtransRequestBody['payment_type'] = "bank_transfer";
                $midtransRequestBody['bank_transfer']['bank'] = $bank;
                break;
            case 'mandiri':
                $midtransRequestBody['payment_type'] = "echannel";
                $midtransRequestBody['echannel']['bill_info1'] = $type;
                $midtransRequestBody['echannel']['bill_info2'] = $grossAmount;
                break;
            case 'permata':
                $midtransRequestBody['payment_type'] = "permata";
                break;
            default:
                return Response::status('failure')->code(422)->result(["Bank type invalid"]);
                break;
        }

        /**
         * Midtrans stuff
         * 
         */
        try {
            $client = new Client();
            $midtransUrl = env('MIDTRANS_SANDBOX_URL')."/charge";
            $midtransServerKey = env('MIDTRANS_SANDBOX_SERVER_KEY');

            $response = $client->request('POST', $midtransUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic '.base64_encode($midtransServerKey.':')
                ],
                'json' => $midtransRequestBody
            ]);
            
            $decodedResponse = json_decode($response->getBody()->getContents());

            // Search data for paymentable_id
            switch($type){
                case 'endowment_funds':
                    $paymentable = $endowment;
                    $paymentableId = $endowment->id;
                    break;
                case 'student_invoices':
                    $paymentable = $studentInvoice;
                    $paymentableId = $studentInvoice->id;
                    break;
            }

            // Insert into payment_callbacks
            PaymentCallback::create([
                'transaction_id' => $decodedResponse->transaction_id,
                'order_id' => $decodedResponse->order_id,
                'response' => $response->getBody(),
                'status' => $decodedResponse->transaction_status,
                'paymentable_type' => $type,
                'paymentable_id' => $paymentableId,
                'payment_type' => "automatic"
            ]);
            
            /**
             * Swithcing response for each bank
             * 
             */
            switch($decodedResponse->status_code){
                case 201:
                    switch($bank){
                        case 'bca':
                        case 'bni':
                        case 'bri':
                            $result = [
                                'bank' => $decodedResponse->va_numbers[0]->bank,
                                'expired_at' => Carbon::parse($decodedResponse->transaction_time)->addDays(1)->format('d M Y h:i'),
                                'va_numbers' => [
                                    'bank' => $decodedResponse->va_numbers[0]->bank,
                                    'va_number' => $decodedResponse->va_numbers[0]->va_number
                                ]
                            ];
                            break;
                        case 'mandiri':
                            $result = [
                                'bank' => 'mandiri',
                                'expired_at' => Carbon::parse($decodedResponse->transaction_time)->addDays(1)->format('d M Y h:i'),
                                'bill_key' => $decodedResponse->bill_key,
                                'biller_code' => $decodedResponse->biller_code
                            ];
                            break;
                        case 'permata':
                            $result = [
                                'bank' => "permata",
                                'expired_at' => Carbon::parse($decodedResponse->transaction_time)->addDays(1)->format('d M Y h:i'),
                                'va_numbers' => [
                                    'bank' => "permata",
                                    'va_number' => $decodedResponse->permata_va_number
                                ]
                            ];
                            break;
                        default:
                            break;
                    }
                    return Response::status('success')->code(200)->result($result);
                    break;
                default:
                    return Response::status('failure')->code($decodedResponse->status_code)->result($decodedResponse);
                    break;
            }
        } catch (RequestException $e) {
            return Response::status('failure')->code(422)->result(["Transaction invalid"]);
        }
    }
}
