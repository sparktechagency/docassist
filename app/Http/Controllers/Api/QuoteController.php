<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log, Mail, Notification};
use App\Models\{Answers, CustomQuote, Questionaries, Quote, RequiredDocuments, ServiceQuote, User};
use App\Http\Controllers\Controller;
use App\Notifications\NewQuoteRequest;
use App\Mail\{CustomQuoteRequestToAdmin, CustomQuoteRequestToCustomer, CustomQuotesReplyMail, ServiceQuoteRequestToAdmin, ServiceQuoteRequestToCustomer};

class QuoteController extends Controller
{
    /**
     * Create a new Quote (Custom or Service based)
     */
    public function createCustomQuote(Request $request)
    {
        // 1. Validation
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'contact_number' => 'required|string|max:20',
            'document_request' => 'required|string',
            'drc' => 'required|string|max:100', // Document Return Country
            'duc' => 'required|string|max:100', // Document Use Country
            'residence_country' => 'required|string|max:100',
        ]);

        try {
            // 2. Create Quote and CustomQuote Records
            $quote = DB::transaction(function () use ($request, $validated) {
                // A. Create Parent Quote
                $quote = Quote::create([
                    'user_id' => $request->user()->id,
                    'type' => 'custom',
                ]);

                // B. Create Custom Quote Details
                CustomQuote::create([
                    'quote_id' => $quote->id,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'contact_number' => $validated['contact_number'],
                    'document_request' => $validated['document_request'],
                    'drc' => $validated['drc'],
                    'duc' => $validated['duc'],
                    'residence_country' => $validated['residence_country'],
                ]);

                return $quote;
            });

            // Load relationships for emails and notifications
            $quote->load(['customQuote', 'user']);
            $customQuote = $quote->customQuote;
            $user = Auth::user();

            // Send database notification
            Notification::send($user, new NewQuoteRequest($quote));

            // Send professional email to customer
            Mail::to($customQuote->email)->send(new CustomQuoteRequestToCustomer($quote));

            // Send professional email to admin
            $adminEmail = env('ADMIN_EMAIL', config('mail.from.address'));
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new CustomQuoteRequestToAdmin($quote));
            }

            // Notify all admins with database notification
            $admins = User::where('role', 'admin')->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewQuoteRequest($quote));
            }

            // Return Success
            return response()->json([
                'status' => true,
                'message' => 'Custom quote created successfully',
                'data' => $quote->load('customQuote'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create custom quote',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

//    public function createServiceQuote(Request $request)
//    {
//        try {
//
//            $validated = $request->validate([
//                'service_id'=>'required|exists:services,id',
//                'delivery_id'=>'required|exists:deliveries,id',
//                'answer' => 'required|array',
//                'answer.*.question_id' => 'required|exists:questionaries,id',
//                'answer.*.value' => 'required',
//                'required_docs' => 'required|array',
//                'required_docs.*' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
//            ]);
//
//
//
//            $quote = Quote::create([
//                    'user_id' => Auth::user()->id,
//                    'type' => 'service',
//                    'delivery_id' => $request->delivery_id,
//                ]);
//
//            $serviceQuote = ServiceQuote::create([
//                    'quote_id' => $quote->id,
//                    'service_id' => $request->service_id,
//                ]);
//
//            dd($serviceQuote);
//
//        }catch (\Exception $e) {
//            return response()->json([
//                'status' => false,
//                'message' => 'Failed to create quote',
//                'error' => $e->getMessage(),
//            ]);
//        }
//    }

    public function createServiceQuote(Request $request)
    {

        // 1. Basic Validation
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'delivery_id' => 'nullable|exists:deliveries,id',
            // Answers is now an array of objects
            'answers' => 'nullable|array',
            'answers.*.question_id' => 'nullable|exists:questionaries,id',
            'answers.*.value' => 'nullable',

            'required_docs' => 'nullable|array',
            'required_docs.*' => 'nullable|array',
            'required_docs.*.*' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        try {
            $result = DB::transaction(function () use ($request) {

                // A. Create Parent Quote
                $quote = Quote::create([
                    'user_id' => Auth::user()->id,
                    'type' => 'service',
                    'delivery_id' => $request->delivery_id,
                ]);

                // B. Create Service Quote Link
                $serviceQuote = ServiceQuote::create([
                    'quote_id' => $quote->id,
                    'service_id' => $request->service_id,
                ]);

                // C. Process Dynamic Answers
//                if ($request->has('answers')) {
//
//                    foreach ($request->answers as $index => $answerData) {
//
//                        // Fetch the question definition to check its type (File vs Text)
//                        $question = Questionaries::findOrFail($answerData['question_id']);
//                        $storedValue = null;
//
//                        // Normalize type for consistent handling
//                        $type = method_exists($question, 'getAttribute') ? ($question->normalized_type ?? strtolower(str_replace(' ', '', $question->type))) : strtolower(str_replace(' ', '', $question->type));
//                        // Case 1: File Upload (only if type explicitly 'file')
//                        if ($type === 'file') {
//                            // Check if the file exists in the request at this specific index
//                            if ($request->hasFile("answers.{$index}.value")) {
////                                $file = $request->file("answers.{$index}.value");
////                                // Store in specific folder
////                                $storedValue = $file->store('documents/quotes', 'public');
//
//                                $storedValue = $this->uploadFile($request->file("answers.{$index}.value"),'documents/quotes');
////                                dd($storedValue);
//                            }
//                        } elseif ($type === 'checkout') {
//                            // Normalize checkbox values (true/false, 'on', '1', etc.)
//                            $raw = $answerData['value'] ?? null;
//                            $storedValue = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
//                        } else {
//                            // Textbox, Input field, Drop down
//                            $storedValue = $answerData['value'] ?? null;
//                        }
//                        // Save to Database
//                        $answers = Answers::create([
//                            'user_id' => Auth::id(),
//                            'service_quote_id' => $serviceQuote->id,
//                            'questionary_id' => $question->id,
//                            'value' => $storedValue,
//                        ]);
//                    }
//                }

                if ($request->has('answers')) {

                    foreach ($request->answers as $index => $answerData) {

                        $question = Questionaries::findOrFail($answerData['question_id']);
                        $storedValue = $answerData['value'] ?? null; // Text value directly save

                        // Save to answers table
                        Answers::create([
                            'user_id' => Auth::id(),
                            'service_quote_id' => $serviceQuote->id,
                            'questionary_id' => $question->id, // Must not be null
                            'docs_id' => null,
                            'value' => $storedValue,
                        ]);
                    }
                }


                // 🆕 D. Handle Required Documents
                $service = $serviceQuote->service;
                $requiredDocs = $service->requiredDocuments()->get();
                if ($requiredDocs->isNotEmpty()) {
                    // Log what we're receiving for debugging
                    Log::info('Required docs processing', [
                        'has_required_docs' => $request->hasFile('required_docs'),
                        'required_docs_input' => $request->input('required_docs'),
                        'all_files' => array_keys($request->allFiles()),
                    ]);

//                    foreach ($requiredDocs as $requiredDoc) {
//
//                        $docKey = "required_docs.{$requiredDoc->id}";
//
//                        if ($request->hasFile($docKey)) {
//
//                            foreach ($request->file($docKey) as $file) {
//
//                                if (!$file instanceof \Illuminate\Http\UploadedFile) {
//                                    continue;
//                                }
//
//                                $storedDocPath = $this->uploadFile(
//                                    $file,
//                                    'documents/quotes/required/'
//                                );
//
//                                Answers::create([
//                                    'user_id' => Auth::id(),
//                                    'service_quote_id' => $serviceQuote->id,
//                                    'docs_id' => $requiredDoc->id,
//                                    'value' => $storedDocPath,
//                                ]);
//                            }
//                        }
//                    }

                    foreach ($requiredDocs as $requiredDoc) {

                        $docKey = "required_docs.{$requiredDoc->id}";

                        if ($request->hasFile($docKey)) {

                            foreach ($request->file($docKey) as $file) {

                                $path = $this->uploadFile(
                                    $file,
                                    'documents/quotes/required/'
                                );

                                Answers::create([
                                    'user_id' => Auth::id(),
                                    'service_quote_id' => $serviceQuote->id,
                                    'questionary_id' => null,
                                    'docs_id' => $requiredDoc->id,
                                    'value' => $path,
                                ]);
                            }
                        }
                    }




//                    foreach ($requiredDocs as $requiredDoc) {
//                        $storedDocPath = null;
//
//                        // Try multiple possible key formats
//                        $docKey = "required_docs.{$requiredDoc->id}";
//
//                        Log::info("Checking for file", [
//                            'doc_id' => $requiredDoc->id,
//                            'doc_key' => $docKey,
//                            'has_file' => $request->hasFile($docKey),
//                        ]);
//
//                        // Check if file is provided for this required document
//                        if ($request->hasFile($docKey)) {
////                            $file = $request->file($docKey);
////                            $storedDocPath = $file->store('documents/quotes/required', 'public');
//
//                            $storedDocPath = $this->uploadFile($request->file($docKey),'documents/quotes/required/');
////                            dd
//                            Log::info("File stored", ['path' => $storedDocPath]);
//                        }
//
//                        // Only create answer record if we have a file or need to track the requirement
//                        Answers::create([
//                            'user_id' => Auth::id(),
//                            'service_quote_id' => $serviceQuote->id,
//                            'questionary_id' => null,
//                            'docs_id' => $requiredDoc->id,
//                            'value' => $storedDocPath,
//                        ]);
//                    }
                }

                return $quote;
            });

            // Load relationships for emails and notifications
            $result->load([
                'serviceQuote.service.category',
                'serviceQuote.service.requiredDocuments',
                'serviceQuote.service.processingTimes',
                'serviceQuote.service.includedServices',
                'serviceQuote.service.questionaries',
                'serviceQuote.answers.questionary',
                'serviceQuote.answers.requiredDocument',
                'user',
                'delivery'
            ]);

            $user = Auth::user();

            // Send database notification
            Notification::send($user, new NewQuoteRequest($result));

            // Send professional email to customer
            Mail::to($user->email)->send(new ServiceQuoteRequestToCustomer($result));

            // Send professional email to admin
            $adminEmail = env('ADMIN_EMAIL', config('mail.from.address'));
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new ServiceQuoteRequestToAdmin($result));
            }

            // Notify all admins with database notification
            $admins = User::where('role', 'admin')->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewQuoteRequest($result));
            }

            // Build structured response
            $questionAnswers = $result->serviceQuote->answers->filter(function ($answer) {
                return !is_null($answer->questionary_id);
            })->map(function ($answer) {
                return [
                    'id' => $answer->id,
                    'question_id' => $answer->questionary_id,
                    'question_title' => $answer->questionary?->name ?? 'N/A',
                    'value' => $answer->value,
                ];
            });

            $documentAnswers = $result->serviceQuote->answers->filter(function ($answer) {
                return !is_null($answer->docs_id);
            })->map(function ($answer) {
                return [
                    'id' => $answer->id,
                    'doc_id' => $answer->docs_id,
                    'doc_title' => $answer->requiredDocument?->title ?? 'N/A',
                    'file_path' => $answer->value,
                ];
            });

            // Return with structured response
            return response()->json([
                'status' => true,
                'message' => 'Quote created successfully',
                'data' => [
                    'quote' => [
                        'id' => $result->id,
                        'user_id' => $result->user_id,
                        'type' => $result->type,
                        'status' => $result->status,
                        'price' => $result->price,
                        'created_at' => $result->created_at,
                        'updated_at' => $result->updated_at,
                    ],
                    'user' => $result->user,
                    'delivery' => $result->delivery,
                    'service' => [
                        'id' => $result->serviceQuote->service->id,
                        'title' => $result->serviceQuote->service->title,
                        'subtitle' => $result->serviceQuote->service->subtitle,
                        'price' => $result->serviceQuote->service->price,
                        'description' => $result->serviceQuote->service->description,
                        'category' => $result->serviceQuote->service->category,
                        'questionaries' => $result->serviceQuote->service->questionaries->map(function ($q) {
                            return [
                                'id' => $q->id,
                                'question' => $q->question,
                                'type' => $q->type,
                            ];
                        }),
                        'required_documents' => $result->serviceQuote->service->requiredDocuments->map(function ($doc) {
                            return [
                                'id' => $doc->id,
                                'title' => $doc->title,
                            ];
                        }),
                        'processing_times' => $result->serviceQuote->service->processingTimes,
                        'included_services' => $result->serviceQuote->service->includedServices,
                    ],
                    'questionnaire_answers' => $questionAnswers->values(),
                    'document_uploads' => $documentAnswers->values(),
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create quote',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

//    public function deleteQuote(Quote $quote)
//    {
//        try {
//            $quote = Quote::findOrFail($quote->id);
//
//            if ($quote->type === 'custom') {
//                CustomQuote::where('quote_id', $quote->id)->delete();
//            }
//            $quote->delete();
//
//            if ($quote->type === 'service') {
//                $serviceQuote = ServiceQuote::where('quote_id', $quote->id)->first();
//                if ($serviceQuote) {
//                    Answers::where('service_quote_id', $serviceQuote->id)->delete();
//                    $serviceQuote->delete();
//                }
//                $serviceQuote->delete();
//            }
//            $quote->delete();
//
//            return response()->json([
//                'status' => true,
//                'message' => 'Quote deleted successfully',
//            ], 200);
//
//        } catch (\Exception $e) {
//            return response()->json([
//                'status' => false,
//                'message' => 'Failed to delete quote',
//                'error' => $e->getMessage(),
//            ], 500);
//        }
//    }

    public function deleteQuote(Quote $quote)
    {
        try {
            // Ensure quote exists
            $quote = Quote::findOrFail($quote->id);

            // Delete related records based on type
            if ($quote->type === 'custom') {
                CustomQuote::where('quote_id', $quote->id)->delete();
            }

            if ($quote->type === 'service') {
                $serviceQuote = ServiceQuote::where('quote_id', $quote->id)->first();
                if ($serviceQuote) {
                    // Delete answers first
                    Answers::where('service_quote_id', $serviceQuote->id)->delete();
                    // Then delete the service quote
                    $serviceQuote->delete();
                }
            }

            // Finally delete the parent quote
            $quote->delete();

            return response()->json([
                'status' => true,
                'message' => 'Quote deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete quote',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function quoteDetails(Quote $quote)
    {
        try {
            // Updated 'serviceQuote.answers' -> 'serviceQuote.answers.questionary'
            $quote = Quote::with([
                'user:id,name,email,profile_pic,role',
                'customQuote',
                'serviceQuote.service',
                'serviceQuote.service.category',
                'serviceQuote.service.requiredDocuments',
                'serviceQuote.service.processingTimes',
                'serviceQuote.service.includedServices',
                'serviceQuote.service.questionaries',
                'serviceQuote.answers.questionary',
                'serviceQuote.answers.requiredDocument',
                'delivery',
            ])->findOrFail($quote->id);

            // Build structured response
            $responseData = [
                'quote' => [
                    'id' => $quote->id,
                    'user_id' => $quote->user_id,
                    'user' => $quote->user,
                    'type' => $quote->type,
                    'status' => $quote->status,
                    'price' => $quote->price,
                    'created_at' => $quote->created_at,
                    'updated_at' => $quote->updated_at,
                ],
                'delivery' => $quote->delivery,
            ];

            // Add custom quote details if exists
            if ($quote->customQuote) {
                $responseData['custom_quote'] = $quote->customQuote;
            }

            // Add service quote details if exists
            if ($quote->serviceQuote) {
                // Separate answers by type
                $questionAnswers = $quote->serviceQuote->answers->filter(function ($answer) {
                    return !is_null($answer->questionary_id);
                })->map(function ($answer) {
                    return [
                        'id' => $answer->id,
                        'question_id' => $answer->questionary_id,
                        'question_title' => $answer->questionary?->name ?? 'N/A',
                        'value' => $answer->value,
                    ];
                });

                $documentAnswers = $quote->serviceQuote->answers->filter(function ($answer) {
                    return !is_null($answer->docs_id);
                })->map(function ($answer) {
                    return [
                        'id' => $answer->id,
                        'doc_id' => $answer->docs_id,
                        'doc_title' => $answer->requiredDocument?->title ?? 'N/A',
                        'file_path' => $answer->value,
                    ];
                });

                $responseData['service_quote'] = [
                    'id' => $quote->serviceQuote->id,
                    'service' => [
                        'id' => $quote->serviceQuote->service->id,
                        'title' => $quote->serviceQuote->service->title,
                        'subtitle' => $quote->serviceQuote->service->subtitle,
                        'price' => $quote->serviceQuote->service->price,
                        'description' => $quote->serviceQuote->service->description,
                        'category' => $quote->serviceQuote->service->category,
                        'questionaries' => $quote->serviceQuote->service->questionaries->map(function ($q) {
                            return [
                                'id' => $q->id,
                                'question' => $q->name,
                                'type' => $q->type,
                            ];
                        }),
                        'required_documents' => $quote->serviceQuote->service->requiredDocuments->map(function ($doc) {
                            return [
                                'id' => $doc->id,
                                'title' => $doc->title,
                            ];
                        }),
                        'processing_times' => $quote->serviceQuote->service->processingTimes,
                        'included_services' => $quote->serviceQuote->service->includedServices,
                    ],
                    'questionnaire_answers' => $questionAnswers->values(),
                    'document_uploads' => $documentAnswers->values(),
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Quote fetched successfully',
                'data' => $responseData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch quote',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function customQuoteList(Request $request)
    {
        $request->validate([
            'filter' => 'nullable|in:New,Mailed',
        ]);
        $filter = $request->query('filter');
        try {
            $perPage = request()->query('per_page', 10);
            $quotes = CustomQuote::with(['quote.user'])->when($filter, function ($query, $filter) {
                $query->where('status', $filter);
            })->latest('id')->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Custom Quotes fetched successfully',
                'data' => $quotes,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch custom quotes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function ServiceQuoteList(Request $request)
    {
                $request->validate([
            'filter' => 'nullable|in:New,Mailed',
        ]);
        $filter = $request->query('filter');

        try {
            $perPage = request()->query('per_page', 10);
            $quotes = Quote::with(['user', 'serviceQuote', 'serviceQuote.service', 'serviceQuote.service.category'])->
            latest('id')->when($filter, function ($query, $filter) {
                $query->where('status', $filter);
            })->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Quotes fetched successfully',
                'data' => $quotes,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch quotes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function replyToQuote(Request $request, $quote)
    {
        $request->validate([
            'reply' => 'required|string',
        ]);

        try {
            $quotes_replied = CustomQuote::with('quote')->findOrFail($quote);
            $quotes_replied->status = 'Mailed';
            $quotes_replied->reply = $request->input('reply');
            $quotes_replied->save();

            // Also update the parent Quote status
            if ($quotes_replied->quote) {
                $quotes_replied->quote->status = 'Mailed';
                $quotes_replied->quote->save();
            }

            Mail::to($quotes_replied->email)
                ->send(new CustomQuotesReplyMail($quotes_replied));

            return response()->json([
                'status' => true,
                'message' => 'Quotes replied successfully',
                'data' => $quotes_replied->load('quote'),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to reply quotes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function replyToServiceQuote(Request $request, $quote)
    {
        $request->validate([
            'reply' => 'required|string',
        ]);

        try {
            $quotes_replied = Quote::with('user')->findOrFail($quote);
            $quotes_replied->status = 'Mailed';
            $quotes_replied->reply = $request->input('reply');
            $quotes_replied->save();

            Mail::to($quotes_replied->user->email)
                ->send(new CustomQuotesReplyMail($quotes_replied));

            return response()->json([
                'status' => true,
                'message' => 'Quotes replied successfully',
                'data' => $quotes_replied,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to reply quotes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
