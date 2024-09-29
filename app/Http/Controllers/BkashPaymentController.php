<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Karim007\LaravelBkash\Facade\BkashPayment;
use Karim007\LaravelBkash\Facade\BkashRefund;

use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Models\CombinedOrder;
use App\Models\BusinessSetting;
use App\Models\Seller;
use Session;

class BkashPaymentController extends Controller
{
    public function index()
    {
        return view('bkash-payment');
        // return view('bkash::bkash-payment');
    }
    
    
    public function getToken()
    {
        session()->put('invoice_amount',100);
        return BkashPayment::getToken();
    }
    
    public function createPayment(Request $request)
    {
        $request['intent'] = 'sale';
        $request['currency'] = 'BDT';
        // $request['amount'] = 1;
        $request['amount'] = session()->get('payment_amount') ??1;
        $request['merchantInvoiceNumber'] = rand();
        $request['callbackURL'] = config("bkash.callbackURL");;
    
        $request_data_json = json_encode($request->all());
    
        return BkashPayment::cPayment($request_data_json);
    }
    
    public function executePayment(Request $request)
    {
        $paymentID = $request->paymentID;
        return BkashPayment::executePayment($paymentID);
    }
    
    public function queryPayment(Request $request)
    {
        $paymentID = $request->payment_info['payment_id'];
        return BkashPayment::queryPayment($paymentID);
    }

    public function bkashSuccess(Request $request)
    {
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
        
        return BkashPayment::bkashSuccess($pay_success);
    }
    
    public function refundPage()
    {
        return BkashRefund::index();
    }
    
    public function refund(Request $request)
    {
        $this->validate($request, [
            'payment_id' => 'required',
            'amount' => 'required',
            'trx_id' => 'required',
            'sku' => 'required|max:255',
            'reason' => 'required|max:255'
        ]);
    
        $post_fields = [
            'paymentID' => $request->payment_id,
            'amount' => $request->amount,
            'trxID' => $request->trx_id,
            'sku' => $request->sku,
            'reason' => $request->reason,
        ];
        return BkashRefund::refund($post_fields);
    }


    
    
}
