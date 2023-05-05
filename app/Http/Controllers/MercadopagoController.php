<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\CombinedOrder;
use Session;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Order;
use App\BusinessSetting;
use App\Seller;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\WalletController;
use PaytmWallet;
use Auth;
use Redirect;
use Illuminate\Routing\UrlGenerator;

class MercadopagoController extends Controller
{
    

    public function paybill()
    {
        $amount=0;

        if(Session::has('payment_type')){
            if(Session::get('payment_type') == 'cart_payment'){
                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
                $amount = round($combined_order->grand_total);
                $combined_order_id = $combined_order->id;
                $billname = 'Ecommerce Cart Payment';
                $first_name = json_decode($combined_order->shipping_address)->name;
                $phone = json_decode($combined_order->shipping_address)->phone;
                $email = json_decode($combined_order->shipping_address)->email;
                $success_url=url('/mercadopago/payment/done');
                $fail_url=url('/mercadopago/payment/cancel');
            }
            elseif (Session::get('payment_type') == 'wallet_payment') {
                $amount = Session::get('payment_data')['amount'] ;
                $combined_order_id = rand(10000,99999);
                $billname = 'Wallet Payment';
                $first_name = Auth::user()->name;
                $phone = (Auth::user()->phone != null) ? Auth::user()->phone : '123456789';
                $email = (Auth::user()->email != null) ? Auth::user()->email : 'example@example.com';
                $success_url=url('/mercadopago/payment/done');
                $fail_url=url('/mercadopago/payment/cancel');

            }
            elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $amount = round($customer_package->amount);
                $combined_order_id = rand(10000,99999);
                $billname = 'Customer Package Payment';
                $first_name = Auth::user()->name;
                $phone = (Auth::user()->phone != null) ? Auth::user()->phone : '123456789';
                $email = (Auth::user()->email != null) ? Auth::user()->email : 'example@example.com';
                $success_url=url('/mercadopago/payment/done');
                $fail_url=url('/mercadopago/payment/cancel');
            }
            elseif (Session::get('payment_type') == 'seller_package_payment') {
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
                $amount = round($seller_package->amount);
                $combined_order_id = rand(10000,99999);
                $billname = 'Seller Package Payment';
                $first_name = Auth::user()->name;
                $phone = (Auth::user()->phone != null) ? Auth::user()->phone : '123456789';
                $email = (Auth::user()->email != null) ? Auth::user()->email : 'example@example.com';
                $success_url=url('/mercadopago/payment/done');
                $fail_url=url('/mercadopago/payment/cancel');
            }
        }

        return view('frontend.payment.mercadopago',compact('combined_order_id','billname','phone','amount','first_name','email','success_url','fail_url'));
    }



    public function paymentstatus()
    {

        $response= request()->status;
        if($response == 'approved')
        {
            $payment = ["status" => "Success"];
            $payment_type = Session::get('payment_type');

            if ($payment_type == 'cart_payment') {
                flash(translate("Your order has been placed successfully"))->success();
                $checkoutController = new CheckoutController;
                return $checkoutController->checkout_done(session()->get('combined_order_id'), json_encode($payment));
            }

            if ($payment_type == 'wallet_payment') {
                $walletController = new WalletController;
                return $walletController->wallet_payment_done(session()->get('payment_data'), json_encode($payment));
            }

            if ($payment_type == 'customer_package_payment') {
                $customer_package_controller = new CustomerPackageController;
                return $customer_package_controller->purchase_payment_done(session()->get('payment_data'), json_encode($payment));
            }
            if($payment_type == 'seller_package_payment') {
                $seller_package_controller = new SellerPackageController;
                return $seller_package_controller->purchase_payment_done(session()->get('payment_data'), json_encode($payment));
            }
        }
        else
            {
                flash(translate('Payment is cancelled'))->error();
                return redirect()->route('home');   
            }
        
    
    }

    public function callback()
    {

       $response= request()->all(['collection_id','collection_status','payment_id','status','preference_id']);
       //Log::info($response);
       flash(translate('Payment is cancelled'))->error();
       return redirect()->route('home');
    }

}
