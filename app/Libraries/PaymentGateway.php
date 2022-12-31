<?php

namespace App\Libraries;

use Braintree\Transaction;

class PaymentGateway
{
    protected $paypal;
    protected $braintree;

    public function payWithBraintree($amount, $currency, $paymentMethodNonce)
    {
        // Create the transaction
        $result = Transaction::sale([
            'amount' => $amount,
            'paymentMethodNonce' => $paymentMethodNonce,
            'options' => [
                'submitForSettlement' => true
            ]
        ]);

        if ($result->success) {
            return [
                'success' => true,
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'error' => $result->message
            ];
        }
    }
}
