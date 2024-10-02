<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Models\CombinedOrder;
use App\Models\BusinessSetting;
use App\Models\Seller;
use Session;

use Exception;
use IrfanChowdhury\BkashTokenizedCheckout\Http\Requests\SubmitRequest;
use IrfanChowdhury\BkashTokenizedCheckout\Services\PaymentService;


class BkashController extends Controller
{
    private $base_url;
    public function __construct()
    {
        // if(get_setting('bkash_sandbox', 1)){
        //     $this->base_url = "https://checkout.sandbox.bka.sh/v1.2.0-beta/";
        // }
        // else {
        //     $this->base_url = "https://checkout.pay.bka.sh/v1.2.0-beta/";
        // }
    }

    public function pay(){
        $amount = 0;
        if(Session::has('payment_type')){
            if(Session::get('payment_type') == 'cart_payment'){

                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
                if(request("payment_option") == "bkash_delevery_charge"){
                    $amount = round(200);
                }else{
                    $amount = round($combined_order->grand_total);
                }

            }
            elseif (Session::get('payment_type') == 'wallet_payment') {
                $amount = round(Session::get('payment_data')['amount']);
            }
            elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $amount = round($customer_package->amount);
            }
            elseif (Session::get('payment_type') == 'seller_package_payment') {
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
                $amount = round($seller_package->amount);
            }
        }

        Session::put('payment_amount', $amount);

        try {
            $paymentService = new PaymentService();
            $payment = $paymentService->initialize("bkash");

            $request = request();
            $request->merge([
                "amount"=>$amount
            ]);

            return $payment->pay($request);
        }
        catch (Exception $e) {

            return redirect()->back()->withErrors(['errors' => $e->getMessage()]);
        }
    }

    public function bkashCallback(PaymentService $paymentService, Request $request)
    {
        try {
            $payment = $paymentService->initialize('bkash');

            $payment->paymentStatusCheck($request);

            session()->put('paymentID', $request->paymentID);

            // Implement your other business logic after payment done.


            $pay_success = $request->payment_info['transactionStatus'];

            $payment_type = Session::get('payment_type');

            if ($payment_type == 'cart_payment') {
                $checkoutController = new CheckoutController;
                 $checkoutController->checkout_done(Session::get('combined_order_id'), $request->payment_details);
            }

            if ($payment_type == 'wallet_payment') {
                $walletController = new WalletController;
                 $walletController->wallet_payment_done(Session::get('payment_data'), $request->payment_details);
            }

            if ($payment_type == 'customer_package_payment') {
                $customer_package_controller = new CustomerPackageController;
                 $customer_package_controller->purchase_payment_done(Session::get('payment_data'), $request->payment_details);
            }
            if($payment_type == 'seller_package_payment') {
                $seller_package_controller = new SellerPackageController;
                 $seller_package_controller->purchase_payment_done(Session::get('payment_data'), $request->payment_details);
            }


            return redirect()->route('order_confirmed')->with(['success' => 'Payment Successfully Done']);
        }
        catch (Exception $e) {

            return redirect()->route('checkout')->withErrors(['errors' => $e->getMessage()]);
        }
    }

    public function checkout(Request $request){
        $auth = Session::get('bkash_token');

        $requestbody = array(
            'amount' => Session::get('payment_amount'),
            'currency' => 'BDT',
            'intent' => 'sale'
        );
        $url = curl_init($this->base_url.'checkout/payment/create');
        $requestbodyJson = json_encode($requestbody);

        $header = array(
            'Content-Type:application/json',
            'Authorization:' . $auth,
            'X-APP-Key:'.env('BKASH_CHECKOUT_APP_KEY')
        );

        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $resultdata = curl_exec($url);
        curl_close($url);

        return $resultdata;
    }

    public function excecute(Request $request){
        $paymentID = $request->paymentID;
        $auth = Session::get('bkash_token');

        $url = curl_init($this->base_url.'checkout/payment/execute/'.$paymentID);
        $header = array(
            'Content-Type:application/json',
            'Authorization:' . $auth,
            'X-APP-Key:'.env('BKASH_CHECKOUT_APP_KEY')
        );

        curl_setopt($url,CURLOPT_HTTPHEADER, $header);
        curl_setopt($url,CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url,CURLOPT_FOLLOWLOCATION, 1);
        $resultdata = curl_exec($url);
        curl_close($url);

        return $resultdata;
    }

    public function success(Request $request){
        $payment_type = Session::get('payment_type');

        if ($payment_type == 'cart_payment') {
            $checkoutController = new CheckoutController;
            return $checkoutController->checkout_done(Session::get('combined_order_id'), $request->payment_details);
        }

        if ($payment_type == 'wallet_payment') {
            $walletController = new WalletController;
            return $walletController->wallet_payment_done(Session::get('payment_data'), $request->payment_details);
        }

        if ($payment_type == 'customer_package_payment') {
            $customer_package_controller = new CustomerPackageController;
            return $customer_package_controller->purchase_payment_done(Session::get('payment_data'), $request->payment_details);
        }
        if($payment_type == 'seller_package_payment') {
            $seller_package_controller = new SellerPackageController;
            return $seller_package_controller->purchase_payment_done(Session::get('payment_data'), $request->payment_details);
        }
    }
}
