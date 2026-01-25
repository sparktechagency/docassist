<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, File, Log, Notification, Validator};
use App\Http\Controllers\Controller;
use App\Models\{Answers, Order, OrderItem, RequiredDocuments, Service, Transaction, User};
use App\Notifications\NewOrderPlaced;
use Stripe\{PaymentIntent, Stripe};

// Optional if you still use the service, but we are using direct Stripe calls here for simplicity as requested.

class CheckoutController extends Controller
{
    public function __construct()
    {
        // Set Stripe API Key globally for this controller
        Stripe::setApiKey(config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));
    }

    // payment intent

    public function paymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => round($request->amount * 100), // amount in cents
                'currency' => 'usd',
                'automatic_payment_methods' => ['enabled' => true],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment Intent created successfully',
                'data' => $paymentIntent,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create Payment Intent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * STEP 1: Initiate Checkout
     * Validates items, calculates total securely, creates pending order, returns Client Secret.
     */
    public function paymentSuccess(Request $request)
    {
//        dd($request->all());
        // 1 Validate
        $request->validate([
            'amount' => 'required',
            'payment_intent_id' => 'nullable|string',
            'is_south_africa' => 'required|boolean',
            'delivery_id' => 'required|exists:deliveries,id',

//            'items' => 'required|array|min:1',
            'service_id' => 'required|exists:services,id',
            'quantity' => 'required|integer|min:1',

            'answers' => 'nullable|array',
            'answers.*.question_id' => 'nullable',
            'answers.*.value' => 'nullable',

            'required_docs' => 'required|array',
            'required_docs.*' => 'required|array',
            'required_docs.*.*' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

//        Log::warning('This is log'.$request->all());
//        return $request->all();
//        dd($request->all());


        $user = Auth::user();
        $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

        try {
            return DB::transaction(function () use ($request, $user, $paymentIntent) {

                // 2 Create Order
                $order = Order::create([
                    'user_id' => $user->id,
                    'orderid' => Order::generateOrderId(),
                    'slug' => 'order-' . Order::generateOrderId(),
                    'total_amount' => $request->amount,
                    'is_south_africa' => $request->is_south_africa,
                    'delivery_id' => $request->delivery_id,
                    'stripe_payment_id' => $paymentIntent->id,
                    'status' => 'pending',
                ]);

//                dd($order);

                $orderItemsByService = [];



//                foreach ($request->items as $itemIndex => $itemData) {
                    $service = Service::findOrFail($request->service_id);

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'service_id' => $service->id,
                        'quantity' => $request->quantity,
                        'price' => $service->price,
                        'subtotal' => $service->price * $request->quantity,
                    ]);

//                    dd($orderItemsByService);
                    $orderItemsByService[$service->id] = $orderItem;

//                    dd($request->answers);
                    // Answers for THIS item
                    if (!empty($request->answers)) {
//                        dd($request->answers);
                        foreach ($request->answers as $index => $answer) {

                            $storedValue = $answer['value'] ?? null;

//                            $fileKey = "answers.{$index}.value";
//                            if ($request->hasFile($fileKey)) {
//                                $storedValue = $this->uploadFile(
//                                    $request->file($fileKey),
//                                    'documents/orders/answers/'
//                                );
//
////                                dd('jhdf');
//                            }

                          $answer =  Answers::create([
                                'user_id' => $user->id,
                                'order_id' => $order->id,
                                'order_item_id' => $orderItem->id,
                                'questionary_id' => $answer['question_id'],
                                'value' => $storedValue,
                            ]);

//                          dd($answer);
                          Log::info('This is log'.$answer);
                        }
                    }

                    // 🆕 Handle Required Documents
                        $requiredDocs = $service->requiredDocuments;

                        foreach ($requiredDocs as $requiredDoc) {
                            $docKey = "required_docs.{$requiredDoc->id}";

                            if ($request->hasFile($docKey)) {
                                foreach ($request->file($docKey) as $file) {

                                    $storedPath = $this->uploadFile(
                                        $file,
                                        'documents/orders/required/'
                                    );
//                                    dd($storedPath);
                                  $testAnser =  Answers::create([
                                        'user_id' => $user->id,
                                        'order_id' => $order->id,
                                        'order_item_id' => $orderItem->id,
                                        'docs_id' => $requiredDoc->id,
                                        'value' => $storedPath,
                                    ]);

                                    $uploadedImages[] = asset($storedPath);
//                                  dd($testAnser);
//                                  Log::info('This is log'.$testAnser);
//                                  dump($testAnser);
                                }
                            }
                        }
//                }

                // 4️⃣ Transaction record
                Transaction::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $request->amount,
                    'status' => 'initiated',
                ]);

                // 5️⃣ Notify admins ans user

                // Notify the user who placed the order
                $user = Auth::user();
                Notification::send($user, new NewOrderPlaced($order));

                // Notify all admins
                $admins = User::where('role', 'admin')->get();
                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new NewOrderPlaced($order));
                }
                return response()->json([
                    'status' => true,
                    'message' => 'Order placed successfully',
                    'data' => [
                        'order' => $order,
                        'orderItemsByService' => $uploadedImages,
                    ],
                ], 201);
            });

        } catch (\Exception $e) {
            Log::error('Order Error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to place order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
