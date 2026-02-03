<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PeachPayment extends Controller
{
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
    public function initiatePayment(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$this->getAccessToken()['access_token'],
//                'Origin' => env('APP_URL'),
                'Referer' => env('APP_URL')
            ])->post($this->baseSandBoxUrl.'/v2/checkout', [
                'authentication.entityId'=>env('PEACH_ENTITY_ID'),
                'merchantTransactionId'=>env('PEACH_MERCHANT_ID'),
                'amount'=>100,
                'currency'=>'ZAR',
                'nonce'=>Str::random(16),
                'shopperResultUrl'=>route('returnUrl'),
            ]);

            return $response;
        }catch (\Exception $exception){
            return $exception->getMessage();
        }
    }

    public function returnUrl(Request $request){
        return 'nthg';
    }
}
