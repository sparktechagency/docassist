<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\CheckoutController;
use App\Models\Answers;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\NewOrderPlaced;
use App\Services\PeachPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
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
            $request->validate([
                'amount' => 'required',
                'is_south_africa' => 'nullable|string',
                'delivery_id' => 'required|exists:deliveries,id',
                'service_id' => 'required|exists:services,id',
                'quantity' => 'required|integer|min:1',
                'answers' => 'nullable|array',
                'answers.*.question_id' => 'nullable',
                'answers.*.value' => 'nullable',
                'required_docs' => 'required|array',
                'required_docs.*' => 'required|array',
                'required_docs.*.*' => 'required|image|mimes:jpg,jpeg,png,webp',
            ]);
//            dd($request->all());
            $grandTotal = $request->input('amount');

            $user = Auth::user();
            $order = Order::create([
                'user_id' => $user->id,
                'orderid' => Order::generateOrderId(),
                'slug' => 'order-' . Order::generateOrderId(),
                'total_amount' => $request->amount,
                'is_south_africa' => $request->is_south_africa === 'yes' ? 1 : 0,
                'delivery_id' => $request->delivery_id,
                'status' => 'pending',
            ]);

            $service = Service::findOrFail($request->service_id);

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'service_id' => $service->id,
                'quantity' => $request->quantity,
                'price' => $service->price,
                'subtotal' => $service->price * $request->quantity,
            ]);


            if (!empty($request->answers)) {
                foreach ($request->answers as $index => $answer) {
                    $answer =  Answers::create([
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'questionary_id' => $answer['question_id'],
                        'value' => $answer['value'] ?? null,
                    ]);
                }
            }

            $uploadedImages = [];
            if (!empty($request->required_docs)) {
                foreach ($request->required_docs as $docId => $files) {
                    if (!empty($files)) {
                        foreach ($files as $file) {
                            if ($file->isValid()) {
                                $storedPath = $this->uploadFile($file, 'documents/orders/required/');
                                Answers::create([
                                    'user_id' => $user->id,
                                    'order_id' => $order->id,
                                    'order_item_id' => $orderItem->id,
                                    'docs_id' => $docId,
                                    'value' => $storedPath,
                                ]);

                                $uploadedImages[] = asset($storedPath);
                            }
                        }
                    }
                }

//                dd($uploadedImages);
            }


            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$this->getAccessToken()['access_token'],
                'Referer' => env('REFERER_URL')
            ])->post($this->baseSandBoxUrl.'/v2/checkout', [
                'authentication.entityId'=>env('PEACH_ENTITY_ID'),
                'merchantTransactionId'=>env('PEACH_MERCHANT_ID'),
                'amount'=>number_format($grandTotal, 2, '.', ''),
                'currency'=>'ZAR',
                'nonce'=>Str::random(16),
                'shopperResultUrl'=>URL::temporarySignedRoute(
                    'returnUrl',
                    now()->addMinutes(10),
                    ['user_id' => Auth::id(),'orderid' => $order->id]
                ),
            ]);

//            Log::info('This is log'. $request->amount);


            Log::info('From Payment Initiate '. json_encode($request->all()));
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
            $user = User::find($request->query('user_id'));
            $order = Order::find($request->query('orderid'));
//            dd($order);
//            dd($user);
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getAccessToken()['access_token'],
            ])->get($this->baseSandBoxUrl . '/v2/checkout/' . $checkout . '/status');

            if ($response->status() == 200) {
                Transaction::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'payment_intent_id' => $request->checkoutId,
                    'amount' => $request->amount,
                    'status' => 'initiated',
                ]);

//                $user = Auth::user();
                Notification::send($user, new NewOrderPlaced($order));

                // Notify all admins
                $admins = User::where('role', 'admin')->get();
                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new NewOrderPlaced($order));
                }
                Log::info('From Return URL '. json_encode($request->all()));
//                return $response->json();
                return redirect(env('FRONTEND_URL').'/payment-success');
            }else{
                return $response->json();
            }
        }catch (\Exception $exception){
            return $exception->getMessage();
        }
    }
}
