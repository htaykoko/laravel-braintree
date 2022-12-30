<?php

namespace App\Http\Controllers;

use App\Libraries\PaymentGateway;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{

    protected $paymentGateway;

    public function __construct(PaymentGateway $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function payForm()
    {
        return view('payform');
    }

    public function payWithPayPal(Request $request)
    {
        $payment = Payment::create([
            'gateway' => 'paypal',
            'amount' => $request->input('amount'),
            'currency' => $request->input('currency'),
            'customer_name' => $request->input('customerName'),
            'response' => json_encode($request->input('paypalResponse'))
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment successful',
            'data' => $payment,
        ]);
    }

    public function payWithBraintree(Request $request)
    {
        // Validate the request data
        $validator = Validator::make(
            $request->all(),
            [
                'amount' => 'required|numeric',
                'currency' => 'required|string',
                'customerName' => 'required|string',
                'paymentMethodNonce' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->all()]);
        }

        // Create the transaction
        $response = $this->paymentGateway->payWithBraintree(
            $request->input('amount'),
            $request->input('currency'),
            $request->input('paymentMethodNonce')
        );

        if ($response['success']) {
            // Save the transaction data to the database
            $payment = Payment::create([
                'gateway' => 'braintree',
                'amount' => $request->input('amount'),
                'currency' => $request->input('currency'),
                'customer_name' => $request->input('customerName'),
                'response' => json_encode($response)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment successful',
                'data' => $payment,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'error' => $response['error'],
                'data' => $request->all(),
            ]);
        }
    }
}
