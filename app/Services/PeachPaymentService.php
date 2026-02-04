<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PeachPaymentService{
    protected $baseSandBoxUrl;

    public function __construct()
    {
        $this->baseSandBoxUrl = 'https://testsecure.peachpayments.com';
    }

    public function getAccessToken()
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://sandbox-dashboard.peachpayments.com/api/oauth/token', [
                "clientId"=>env('PEACH_CLIENT_ID'),
                "clientSecret"=>env('PEACH_CLIENT_SECRET'),
                "merchantId"=>env('PEACH_MERCHANT_ID'),
            ]);

            return $response->json();
        }catch (\Exception $exception){
            return $exception->getMessage();
        }
    }
    public function initiatePayment($grandTotal)
    {
        try {

//            return $grandTotal;
//            dd($this->getAccessToken()['access_token']);
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$this->getAccessToken()['access_token'],
//                'Origin' => env('APP_URL'),
                'Referer' => 'http://10.10.10.90'
            ])->post($this->baseSandBoxUrl.'/v2/checkout', [
                'authentication.entityId'=>env('PEACH_ENTITY_ID'),
                'merchantTransactionId'=>env('PEACH_MERCHANT_ID'),
                'amount'=>number_format($grandTotal, 2, '.', ''),
                'currency'=>'ZAR',
                'nonce'=>Str::random(16),
                'shopperResultUrl'=>route('returnUrl'),
            ]);

            return response()->json([
                'status'=>true,
                'message'=>'Payment initiated',
                'data'=>$response->json()
            ]);
        }catch (\Exception $exception){
            return $exception->getMessage();
        }
    }

    public function returnUrl(Request $request){
        try {
            $checkout = $request->checkoutId;
//            dd($checkout);
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getAccessToken()['access_token'],
            ])->get($this->baseSandBoxUrl . '/v2/checkout/' . $checkout . '/status');

            if ($response->status() == 200) {
                return redirect(env('FRONTEND_URL').'/payment-success');
            }else{
                return $response->json();
            }
        }catch (\Exception $exception){
            return $exception->getMessage();
        }
    }
}
