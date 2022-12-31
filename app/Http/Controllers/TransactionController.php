<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\PaymentType;
use App\Models\Transaction;
use App\Services\PaypalService;
use App\Services\StripeService;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\NotFoundException;
use App\Services\CaishengPayService;
use Carbon\Carbon;
use Facade\FlareClient\Http\Exceptions\NotFound;
use Illuminate\Support\Facades\Auth as Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Laravel\Ui\Presets\React;
use App\Filters\TransactionFilter;
use App\Http\Traits\ZhifulePay;
use App\Models\Subscription;
use App\Repositories\ApplePayRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\PaypalRepository;
use App\Repositories\CaishengPayRepository;
use App\Repositories\PaymentTypeRepository;
use App\Repositories\VpnUserRepository;
use DateTime;
use Illuminate\Support\Facades\Http;

class TransactionController extends Controller
{
    public function __construct(PaymentTypeRepository $paymentTypeRepo, PaypalService $payPalService, CaishengPayService $caishengService, TransactionRepository $transactionRepo, PaypalRepository $paypalRepo, CaishengPayRepository $caishengPayRepo, VpnUserRepository $vpnUserRepo, ApplePayRepository $applePayRepo)
    {
        $this->payPalService = $payPalService;
        $this->caishengService = $caishengService;
        $this->transactionRepo = $transactionRepo;
        $this->paypalRepo = $paypalRepo;
        $this->caishengPayRepo = $caishengPayRepo;
        $this->paymentTypeRepo = $paymentTypeRepo;
        $this->vpnUserRepo = $vpnUserRepo;
        $this->applePayRepo = $applePayRepo;
        $this->serverLoadedMsg = config('message.serverLoadedMsg');
        $this->perPage = config('enums.itemPerPage');
    }

    public function index(TransactionFilter $filter)
    {
        $transactions = $this->transactionRepo->getPaginatedWithFilter($this->perPage, $filter);
        $paymentTypes = $this->paymentTypeRepo->getPaymentTypeList();
        return view('transaction', compact('transactions', 'paymentTypes'));
    }

    public function getPaymenDetails(Request $request)
    {
        $details = [];
        $details['paymentMethod'] = $this->paymentTypeRepo->getById($request->id)->name;

        if ($request->id == config('enums.paymentMethod.paypal')) {
            $payPal = $this->paypalRepo->getPaymentDetails($request->transactionId);

            $details['accountName'] = optional($payPal)->account_name;
            $details['accountId'] = optional($payPal)->account_id;
            $details['accountEmail'] = optional($payPal)->account_email;
            $details['amount'] = "$ " .  optional($payPal)->gross_amount;
            $details['transactionFee'] = "$ " . optional($payPal)->transaction_fee;
            $details['netAmt'] = "$ " . optional($payPal)->net_amount;
        }

        if ($request->id == config('enums.paymentMethod.alipay') || $request->id == config('enums.paymentMethod.wechatpay')) {
            $caishengPay = $this->caishengPayRepo->getPaymentDetails($request->transactionId);

            $details['accountName'] = null;
            $details['accountId'] = null;
            $details['accountEmail'] = null;
            $details['amount'] = optional($caishengPay)->amount_to_submit;
            $details['transactionFee'] = "$ " . optional($caishengPay)->handling_fee;
            $details['netAmt'] = "$ " . optional($caishengPay)->actual_amount;
        }
        if ($request->id == config('enums.paymentMethod.applepay')) {
            $applePay = $this->applePayRepo->getPaymentDetails($request->transactionId);

            $details['accountName'] = null;
            $details['accountId'] = null;
            $details['accountEmail'] = null;
            $details['amount'] = "$ " . optional($applePay)->amount;
            $details['transactionFee'] = null;
            $details['netAmt'] = null;
        }
        if ($request->id == config('enums.paymentMethod.usdt')) {
            $caishengPay = $this->caishengPayRepo->getPaymentDetails($request->transactionId);

            $details['accountName'] = null;
            $details['accountId'] = null;
            $details['accountEmail'] = null;
            $details['amount'] = optional($caishengPay)->amount_to_submit;
            $details['transactionFee'] = optional($caishengPay)->handling_fee;
            $details['netAmt'] = optional($caishengPay)->actual_amount;
        }
        return $this->printJson("Success", true, $details);
    }

    //Check Payment
    public function processTransaction(Request $request)
    {
        if (!$request->packageId) {
            return redirect()
                ->route('user.home')
                ->with('error', trans('home.Please_Select_Package'));
        }

        if (!$request->paymentId) {
            return redirect()
                ->route('user.home')
                ->with('error', 'Please Select Payment Method.');
        }
        //#### to check premium package existb or not####

        $subscription = Subscription::where('vpn_user_id', Auth::guard('user')->user()->id)->get()->last();
        if ($subscription != null) {
            if ($subscription->type == 'Premium') {
                $current_time = strtotime(Carbon::now());
                $start_time = strtotime($subscription->start_time);
                $different = round(abs($current_time - $start_time) / 60, 2);
                $remaining_time = round($subscription->package_duration - $different);
                if ($remaining_time > 0 && $remaining_time > $subscription->referrer_duration) {
                    return redirect()
                        ->route('user.home')
                        ->with('error', $link['msg'] ?? trans('home.You_Have_Already_Package'));
                }
            }
        }

        $payment = PaymentType::find($request->paymentId);

        if ($payment->id == config('enums.paymentMethod.paypal')) {
            $link = $this->makePayPal($request, $payment);
            return redirect()->away($link);
        }
        // if ($payment->id == config('enums.paymentMethod.visa_master')) {
        //     $transaction = $this->makeStripeTransaction($request);
        //     if ($transaction == false) {
        //         return $this->printJson("Package Not found", false, $response = null);
        //     }
        //     return $this->printJson("CREATED", true, $transaction);
        // }
        $package = Package::find($request->packageId);
        if ($package == null) {
            throw new NotFoundException("Not Found Package");
        }

        // Zhifule Pay : Alipay, WeChat Pay
        if ($payment->id == config('enums.paymentMethod.alipay') || $payment->id == config('enums.paymentMethod.wechatpay')) {
            $dataArray = [
                "type" => $payment->id == config('enums.paymentMethod.alipay') ? 'alipay' : 'wxpay',
                "money" => $package->price,
                "out_trade_no" => Carbon::now()->format('YmdHis') . substr(md5(mt_rand()), 0, 6),
                'pid' => env("ALI_MERCHANT_ID"),
                'notify_url' => route('zhufule.notiUrl'),
                'return_url' => route('zhufule.returnUrl'),
                'name' => 'ZhiFule Pay',
            ];

            $userDataArray = [
                'user_name' => auth('user')->user()->id,
                'package_id' => $package->id
            ];

            $this->zhifulePay($dataArray, $userDataArray);
            // $link = $this->caishengPay($request, $payment);

            // dd($link['payHtml']);
            // if ($link  && $link['payHtml']) {
            //     return redirect()
            //         ->route('user.home')
            //         ->with('qr_code', $link['payHtml']);
            // } else {
            //     return redirect()
            //         ->route('user.home')
            //         ->with('error', $link['msg'] ?? 'Please recheck ,your payment is something wrong!!!');
            // }
        }
        if ($payment->id == config('enums.paymentMethod.usdt')) {
            $link = $this->makeCryptoPay($request, $payment);
            return redirect()->away($link);
            // return redirect()
            //     ->route('user.home')
            //     ->with('qr_code', $link);
        }
    }

    //Make payment with PayPal
    public function makePayPal($request, $payment)
    {
        $credentials = $this->paypalCredential();
        $provider = new PayPalClient();
        $provider->setApiCredentials($credentials);

        $paypalToken = $provider->getAccessToken();
        $transactionId = Carbon::now()->format('YmdHis') . substr(md5(mt_rand()), 0, 6);

        $package = Package::find($request->packageId);

        if ($package == null) {
            throw new NotFoundException("Not Found Package");
        }
        $this->createTransaction($package, $payment, $transactionId);

        $response = $provider->createOrder([
            "intent" => "CAPTURE",
            "application_context" => [
                "return_url" => route('paypalSuccessTransaction'),
                "cancel_url" => route('paypalCancelTransaction'),
            ],
            "purchase_units" => [
                0 => [
                    "amount" => [
                        "currency_code" => "USD",
                        "value" => $package->price,
                    ],
                    "reference_id" =>  $transactionId
                ]
            ]
        ]);
        if (isset($response['id']) && $response['id'] != null) {
            // redirect to approve href
            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return $links['href'];
                }
            }
        } else {
            throw new NotFoundException($response['status']);
        }
    }

    /**
     * firstly create transaction before pay
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function makeStripeTransaction($request)
    {
        return $this->stripeService->createTransaction($request);
    }

    public function paypalSuccessTransaction(Request $request)
    {
        $credential = $this->paypalCredential();
        $provider = new PayPalClient();
        $provider->setApiCredentials($credential);
        $provider->getAccessToken();
        $response = $provider->capturePaymentOrder($request['token']);
        Log::info($response);
        if (isset($response['status']) && $response['status'] == 'COMPLETED') {
            $paypal = $this->transactionRepo->savePayPal($response);
            Log::info($paypal);
            $transaction = Transaction::where('transaction_no', $response['purchase_units'][0]['reference_id'])->first();
            $this->vpnUserRepo->unRevokeVpnServer($transaction);
            return redirect()
                ->route('user.home')
                ->with('success', 'Your Payment is Completed Successfully.');
        } else {
            $order = $provider->showOrderDetails($request['token']);
            $this->payPalService->failedTransaction($order['purchase_units'][0]['reference_id']);
            return redirect()
                ->route('user.home')
                ->with('error', $response['status'] ?? 'Please recheck ,your payment is something wrong!!!');
        }
    }

    public function paypalCancelTransaction(Request $request)
    {
        $credential = $this->paypalCredential();
        $provider = new PayPalClient();
        $provider->setApiCredentials($credential);
        $provider->getAccessToken();
        $response = $provider->capturePaymentOrder($request['token']);
        Log::info($response);
        if (isset($response['error']) && $response['error']['name'] == 'UNPROCESSABLE_ENTITY') {
            $order = $provider->showOrderDetails($request['token']);
            $this->payPalService->cancelTransaction($order['purchase_units'][0]['reference_id']);
            return redirect()
                ->route('user.home')
                ->with('success', 'You have canceled the payment.');
        } else {
            return redirect()
                ->route('user.home')
                ->with('error', $response['error']['message'] ?? 'Please recheck ,your payment is something wrong!!!');
        }
    }

    public function paypalCredential()
    {
        return [
            'mode'    => $paypal->mode, // Can only be 'sandbox' Or 'live'. If empty or invalid, 'live' will be used.
            'sandbox' => [
                'client_id'         => $paypal->client_id,
                'client_secret'     => $paypal->secret_key,
                'app_id'            => $paypal->app_id,
            ],
            'live' => [
                'client_id'         => $paypal->client_id,
                'client_secret'     => $paypal->secret_key,
                'app_id'            => $paypal->app_id,
            ],

            'payment_action' => 'Sale', // Can only be 'Sale', 'Authorization' or 'Order'
            'currency'       => 'USD',
            'notify_url'     => '', // Change this accordingly for your application.
            'locale'         => 'en_US', // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
            'validate_ssl'   => true, // Validate SSL when creating api client.
        ];
    }

    public function caishengPay(Request $request, $payment)
    {
        switch ($payment->id) {
            case config('enums.paymentMethod.alipay'):
                $fxpay = 502;
                break;
            case config('enums.paymentMethod.wechatpay'):
                $fxpay = 504;
                break;
        }
        $transactionId = Carbon::now()->format('YmdHis') . substr(md5(mt_rand()), 0, 6);
        $package = Package::find($request->packageId);
        if ($package == null) {
            throw new NotFoundException("Not Found Package");
        }
        $this->createTransaction($package, $payment, $transactionId);

        $amount = $package->price;

        $data = [
            'out_trade_id' => $transactionId,
            'amount' => sprintf('%.2f', $amount),
            'notify_url' => route('caishengPaySuccessTransaction'),
            'return_url' => route('caishengPaySuccessTransaction'),
            'merchant_id' => $payment->client_id,
            'product_id' => $fxpay,
        ];

        $data['sign'] = $this->sign2($data);
        $data['is_form'] = 1;
        $data['param'] = '0';

        $result_s = $this->CURL('http://www.caishenglizfpay123.com/pay/service/add', $data, 'POST');

        $result = json_decode($result_s, true); //json转数组




        if ($result['code'] == 1) {
            $data = [
                // 'realPayPrice' =>$amount,
                // 'thirdOrderId' =>$transactionId,
                // 'result'=>0,
                // 'payName'=>'new',
                // 'isJump'=>1,
                'payHtml' => $result['data']['pay_url']
            ];
            //echo '验签成功</br>';
            //echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } else {
            $data = [
                'msg' => $result["msg"],
                'result' => 2,
            ];
            // exit;
        }
        return $data;
    }

    public function sign2($arr)
    {
        ksort($arr, SORT_STRING);
        $str = '';
        foreach ($arr as $key => $val) {
            if ($val != '' && $val != 'null' && $val != null && $key != 'sign') {
                $str .= $key . "=" . $val . '&';
            }
        }
        $newtsr = substr($str, 0, strlen($str) - 1);
        $newtsr .= '4LB3WB1OG6M1IH9Y2JXQYLXI8Y2LUS26D8R5ZP53X6OD3KD027UNGYPWEIV3MCZ5B88A22IG9F5L86ZSPTIF0R5CMCXI3M2TP0FUYP8UHKFMTZ38YFLZDZNG9258H3E6';
        // dd($newtsr);
        // var_dump($newtsr);
        return md5($newtsr);
    }
    public function CURL($url, $data, $style = '', $header = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_URL, $url);

        $header[] = 'Expect:';
        if ($style == 'json') {
            $header[] = 'Content-Type:application/json';
            $data = json_encode($data);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        if ($data) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POST, false);
        }
        if (stripos($url, 'https://') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);   // 跳过证书检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);   // 从证书中检查SSL加密算法是否存在
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        if ($res === false) {
            return  sprintf('Curl error (code %s): %s', curl_errno($ch), curl_error($ch));
        }
        curl_close($ch);
        return $res;
    }
    public function caishengPaySuccessTransaction(Request $request)
    {
        Log::info($request->all());


        if ($request['code'] == 1) {
            if ($request['data']['status'] == 1) {
                $caisheng = $this->caishengService->saveCaisheng($request);
                Log::info($caisheng);
                $transaction = Transaction::where('transaction_no', $request['data']['out_trade_id'])->first();
                $this->vpnUserRepo->unRevokeVpnServer($transaction);

                return redirect()
                    ->route('user.home')
                    ->with('success', 'Your Payment is Completed Successfully.');
            } else {
                $caisheng = $this->caishengService->failedCaisheng($request['data']['out_trade_id']);
                Log::info($caisheng);
                return redirect()
                    ->route('user.home')
                    ->with('error', $response['status'] ?? 'Please recheck ,your payment is something wrong!!!');
            }
        } else {
            $caisheng = $this->caishengService->cancelCaisheng($request['data']['out_trade_id']);
            Log::info($caisheng);
            return redirect()
                ->route('user.home')
                ->with('success', 'You have canceled the payment.');
        }
    }

    public function makeCryptoPay($request, $payment)
    {
        $transactionId = Carbon::now()->format('YmdHis') . substr(md5(mt_rand()), 0, 8);
        $package = Package::find($request->packageId);

        if ($package == null) {
            throw new NotFoundException("Not Found Package");
        }
        $this->createTransaction($package, $payment, $transactionId);

        $payid19 = new \Payid19\ClientAPI($payment->client_id, $payment->secret_key); //(public,private)
        $request = [
            'price_amount' => $package->price,
            'price_currency' => 'USD',
            'merchant_id' => $payment->app_id,
            'order_id' => $transactionId,
            'cancel_url' => route('cancelUsdtPay'),
            'success_url' => route('successUsdtPay'),
            'callback_url' => route('callbackUsdtPay'), //'	https://webhook.site/4e229f7f-3c90-49a0-8b35-6f2ebe2d7f48',
            'title' => 'Payment with USDT For Package',
            'description' => 'pay with usdt',
            //'test' => 1,
            // 'add_fee_to_price' => 0,
        ];
        $result = $payid19->create_invoice($request);

        if (json_decode($result)->status == 'error') {
            //error
            $this->transactionRepo->failedTransaction($transactionId);
            throw new NotFoundException(json_decode($result)->message[0]);
        } else {
            //success echo url
            return json_decode($result)->message;
        }
    }
    public function successUsdtPay(Request $request)
    {
        Log::info("Success data from web  " . $request);
        return redirect()
            ->route('user.home')
            ->with('success', 'Your Payment is Completed Successfully.');
    }
    public function cancelUsdtPay(Request $request)
    {
        Log::info("Cancel usdt from web" . $request);
        return redirect()
            ->route('user.home')
            ->with('success', 'You have canceled the payment.');
    }
    public function callbackUsdtPay(Request $request)
    {
        Log::info("Call back request from web" . $request);

        $payment = PaymentType::where('id', config('enums.paymentMethod.usdt'))->first();
        if ($request->privatekey == $payment->secret_key) {
            $usdt = $this->transactionRepo->saveUsdt($request);
            Log::info($usdt);
            $transaction = Transaction::where('transaction_no', $request->order_id)->first();
            $this->vpnUserRepo->unRevokeVpnServer($transaction);

            // $payid19 = new \Payid19\ClientAPI($payment->client_id, $payment->secret_key); //(public,private)
            // $post = [
            //     'public_key' => $payment->client_id,
            //     'private_key' => $payment->secret_key,
            //     'coin' => 'USDT',
            //     'network' => 'TRC20',
            //     'address' => $payment->wallet_address,
            //     'amount' => $request->price_amount
            // ];
            // $withdraw = json_decode($payid19->create_withdraw($post));
            // log::info('Withdraw from web' . $withdraw);
            // $this->transactionRepo->saveUsdtWithdraw($withdraw, $payment->wallet_address);
        }
    }
    public function createTransaction($package, $payment, $transactionId)
    {
        $data = [
            "vpn_user_id" => Auth::guard('user')->user()->id,
            "transaction_no" => $transactionId,
            "package_id" => $package->id,
            "package_price" => $package->price,
            "package_duration" => $package->duration,
            "status" => config('enums.paymentStatus.pending'),
            'payment_type_id' => $payment->id,
        ];
        Transaction::create($data);
    }
}
