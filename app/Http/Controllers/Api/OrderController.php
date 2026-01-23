<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use App\Http\Controllers\Controller;
use App\Models\{Order, Transaction};
use App\Notifications\OrderCompleted;

class OrderController extends Controller
{
    /**
     * 1. USER: List my own orders
     * GET /api/user/my-orders
     * GET /api/user/my-orders?status=completed
     * GET /api/user/my-orders?status=pending
     * GET /api/user/my-orders?search=800891
     */
    public function userOrders(Request $request)
    {
        try {
            $user = Auth::user();

            $perPage = $request->query('per_page', 10);

            // Build query for THIS user's orders only
            $query = Order::with([
                'items.service',
                'items.service.category',
                'items.answers',
                'items.answers.questionary',
                'transactions',
                'rating',
                'delivery'
            ])
                ->where('user_id', $user->id);

            // SEARCH Logic (Search by orderid)
            if ($request->search) {
                $searchTerm = $request->search;
                $query->where('orderid', 'like', "%{$searchTerm}%");
            }

            // STATUS Logic
            if ($request->has('status')) {
                if ($request->status === 'completed') {
                    $query->where('status', 'completed');
                } elseif ($request->status === 'pending') {
                    // "Pending" includes both 'pending' and 'paid' statuses
                    $query->whereIn('status', ['pending', 'paid']);
                } elseif ($request->status === 'all') {
                    // No filter, show all
                } else {
                    // Direct status match for any other value
                    $query->where('status', $request->status);
                }
            }

            // Order & Pagination
            $orders = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'User orders fetched successfully',
                'data' => $orders,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * ADMIN: List Orders (Filterable & Searchable)
     * GET /api/admin/orders?status=completed
     * GET /api/admin/orders?status=pending
     * GET /api/admin/orders?search=839201
     */
    public function adminOrders(Request $request)
    {
        try {
            // 1. Start with the Base Query and Eager Load EVERYTHING
            // We include 'answer' because that contains the user's specific inputs (Age, Docs)
            $query = Order::with([
                'user',
                'answer',
                'items.service',
                'items.service.category',
                'items.service.requiredDocuments',
                'items.service.processingTimes',
                'items.service.includedServices',
                'items.answers',
                'items.answers.questionary',
                'transactions',
                'delivery'
            ]);

            // 2. SEARCH Logic (Search by orderid)
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                // Using 'like' allows for partial matches (e.g. searching "839" finds "839201")
                $query->where('orderid', 'like', "%{$searchTerm}%");
            }

            // 3. STATUS Logic (The "Switch")
            if ($request->has('status')) {
                if ($request->status === 'completed') {
                    // Strict check for completed
                    $query->where('status', 'completed');
                } elseif ($request->status === 'pending') {
                    // "Pending" view includes both 'pending' (unpaid) and 'paid' (processing)
                    // basically anything that is NOT completed
                    $query->whereIn('status', ['pending', 'paid']);
                }
            }

            // 4. Order & Pagination
            $perPage = $request->query('per_page', 10);
            
            $orders = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

           
            return response()->json([
                'status' => true,
                'message' => 'Orders fetched successfully',
                'data' => $orders,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 3. SHARED: View Single Order Details
     * GET /api/orders/{id}
     */
    public function details($id)
    {
        try {
            $user = Auth::user();

            // Allow lookup by numeric id or public orderid
            $order = Order::with([
                'user',
                'items.service',
                'items.service.category',
                'items.service.requiredDocuments',
                'items.service.processingTimes',
                'items.service.includedServices',
                'items.service.questionaries',
                'items.service.questionaries.answers',
                'items.answers',
                'items.answers.questionary',
                'items.answers.requiredDocument',
                'transactions',
                'delivery'
            ])
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('orderid', $id);
            })
            ->firstOrFail();

            // Security: If not admin, ensure user owns this order
            if ($user->role !== 'admin' && $order->user_id !== $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Build structured response
            return response()->json([
                'status' => true,
                'message' => 'Order retrieved successfully',
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'orderid' => $order->orderid,
                        'user_id' => $order->user_id,
                        'user' => $order->user,
                        'total_amount' => $order->total_amount,
                        'is_south_africa' => $order->is_south_africa,
                        'status' => $order->status,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ],
                    'delivery' => $order->delivery,
                    'items' => $order->items->map(function ($item) {
                        // Separate answers by type: questions vs required documents
                        $questionAnswers = $item->answers->filter(function ($answer) {
                            return !is_null($answer->questionary_id);
                        })->map(function ($answer) {
                            return [
                                'id' => $answer->id,
                                'question_id' => $answer->questionary_id,
                                'question_title' => $answer->questionary?->question ?? 'N/A',
                                'value' => $answer->value,
                            ];
                        });

                        $documentAnswers = $item->answers->filter(function ($answer) {
                            return !is_null($answer->docs_id);
                        })->map(function ($answer) {
                            return [
                                'id' => $answer->id,
                                'doc_id' => $answer->docs_id,
                                'doc_title' => $answer->requiredDocument?->title ?? 'N/A',
                                'file_path' => $answer->value,
                            ];
                        });

                        return [
                            'id' => $item->id,
                            'order_id' => $item->order_id,
                            'service_id' => $item->service_id,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'subtotal' => $item->subtotal,
                            'service' => [
                                'id' => $item->service->id,
                                'title' => $item->service->title,
                                'subtitle' => $item->service->subtitle,
                                'price' => $item->service->price,
                                'description' => $item->service->description,
                                'category' => $item->service->category,
                                'questionaries' => $item->service->questionaries->map(function ($q) {
                                    return [
                                        'id' => $q->id,
                                        'question' => $q->question,
                                        'type' => $q->type,
                                    ];
                                }),
                                'required_documents' => $item->service->requiredDocuments->map(function ($doc) {
                                    return [
                                        'id' => $doc->id,
                                        'title' => $doc->title,
                                    ];
                                }),
                                'processing_times' => $item->service->processingTimes,
                                'included_services' => $item->service->includedServices,
                            ],
                            'questionnaire_answers' => $questionAnswers->values(),
                            'document_uploads' => $documentAnswers->values(),
                        ];
                    }),
                    'transactions' => $order->transactions,
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function completeOrder(Request $request, $orderId)
    {
        try {
            $order = Order::findOrFail($orderId);

            // Update order status to 'completed'
            $order->status = 'completed';
            $order->save();

            // Send Notification to User
            $user = $order->user;
            $order->user->notify(new OrderCompleted($order));

            return response()->json([
                'status' => true,
                'message' => 'Order completed successfully',
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    


    public function transactionsHistory(Request $request)
    {
        try {
            $user = Auth::user();

            $perPage = $request->query('per_page', 10);

            $transactions = Transaction::with('order.items.service')->where('user_id',Auth::user()->id)->latest('id')->paginate($perPage);
    
            return response()->json([
                'status' => true,
                'message' => 'Transaction history fetched successfully',
                'data' => $transactions,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

}
