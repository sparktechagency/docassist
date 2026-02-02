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
        $this->baseSandBoxUrl = 'https://testsecure.peachpayments.com/v2/checkout';
    }
    public function initiatePayment(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->baseSandBoxUrl, [
                'authentication.entityId'=>env('PEACH_ENTITY_ID'),
                'merchantTransactionId'=>env('PEACH_MERCHANT_ID'),
                'amount'=>100,
                'currency'=>'ZAR',
                'nonce'=>Str::random(16),
                'shopperResultUrl'=>route('returnUrl'),
            ]);

            return $response->json();
        }catch (\Exception $exception){
            return $exception->getMessage();
        }
    }

    public function returnUrl(Request $request){
        return 'nthg';
    }
}
