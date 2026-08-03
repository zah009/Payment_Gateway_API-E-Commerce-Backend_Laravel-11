<?php

namespace App\Http\Controllers\Api;
use App\Services\MidtransSignatureVerifier;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    /**
     * Create payment & get Midtrans Snap Token
     */
    #[OA\Post(
        path: "/payment/{orderId}",
        tags: ["Payment"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "orderId", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 201, description: "Snap token created"),
            new OA\Response(response: 404, description: "Order not found"),
            new OA\Response(response: 400, description: "Payment already processed"),
        ]
    )]
    public function create(Request $request, string $orderId)
    {
        try {
            // Cari order
            $order = Order::with('items.product')
                ->where('user_id', $request->user()->id)
                ->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            if (!$order->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already processed for this order',
                ], 400);
            }

            // Check if payment already exists
            $existingPayment = Payment::where('order_id', $order->id)->first();
            if ($existingPayment && $existingPayment->snap_token) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already created',
                    'data' => [
                        'snap_token' => $existingPayment->snap_token,
                        'order' => $order,
                        'payment' => $existingPayment,
                    ],
                ]);
            }

            // Setup Midtrans Configuration
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');
            \Midtrans\Config::$isSanitized = config('midtrans.is_sanitized');
            \Midtrans\Config::$is3ds = config('midtrans.is_3ds');

            // Log konfigurasi untuk debug
            Log::info('Midtrans Config', [
                'server_key' => substr(config('midtrans.server_key'), 0, 10) . '...',
                'is_production' => config('midtrans.is_production'),
                'is_sanitized' => config('midtrans.is_sanitized'),
                'is_3ds' => config('midtrans.is_3ds'),
            ]);

            // Prepare transaction details
            $transactionDetails = [
                'order_id' => $order->order_number,
                'gross_amount' => (int) round($order->total_amount), // Pastikan integer bulat
            ];

            // Prepare item details
            $itemDetails = [];
            $totalItemsAmount = 0;

            foreach ($order->items as $item) {
                $price = (int) round($item->product_price);
                $quantity = (int) $item->quantity;
                $subtotal = $price * $quantity;

                $itemDetails[] = [
                    'id' => (string) $item->product_id,
                    'price' => $price,
                    'quantity' => $quantity,
                    'name' => substr($item->product_name, 0, 50), // Max 50 karakter
                ];

                $totalItemsAmount += $subtotal;
            }

            // Validasi total amount harus sama
            if ($totalItemsAmount !== $transactionDetails['gross_amount']) {
                Log::warning('Amount mismatch', [
                    'gross_amount' => $transactionDetails['gross_amount'],
                    'items_total' => $totalItemsAmount,
                ]);
            }

            // Prepare customer details
            $customerDetails = [
                'first_name' => substr($request->user()->name, 0, 50),
                'email' => $request->user()->email,
                'phone' => $request->user()->phone ?? '081234567890',
            ];

            // Prepare Midtrans parameters
            $params = [
                'transaction_details' => $transactionDetails,
                'item_details' => $itemDetails,
                'customer_details' => $customerDetails,
                'enabled_payments' => [
                    'credit_card',
                    'gopay',
                    'shopeepay',
                    'qris',
                    'bca_va',
                    'bni_va',
                    'bri_va',
                    'permata_va',
                    'other_va',
                    'echannel',
                    'akulaku',
                ],
            ];

            // Log params untuk debug (data customer di-mask, jangan log PII mentah)
            Log::info('Creating Midtrans Payment', [
                'order_number' => $order->order_number,
                'gross_amount' => $transactionDetails['gross_amount'],
                'items_count' => count($itemDetails),
                'params' => [
                    'transaction_details' => $transactionDetails,
                    'item_details' => $itemDetails,
                    'customer_details' => [
                        'first_name' => substr($customerDetails['first_name'], 0, 1) . '***',
                        'email' => preg_replace('/(?<=.{2}).(?=[^@]*?@)/', '*', $customerDetails['email']),
                        'phone' => substr($customerDetails['phone'], 0, 4) . '****' . substr($customerDetails['phone'], -2),
                    ],
                    'enabled_payments' => $params['enabled_payments'],
                ],
            ]);

            // Get Snap Token from Midtrans
            $snapToken = \Midtrans\Snap::getSnapToken($params);

            Log::info('Snap Token Created', [
                'order_number' => $order->order_number,
                'snap_token' => substr($snapToken, 0, 20) . '...',
            ]);

            // Create or update payment record
            $payment = Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'gross_amount' => $order->total_amount,
                    'payment_status' => 'pending',
                    'snap_token' => $snapToken,
                    'expired_at' => now()->addDay(),
                ]
            );

            // Log payment creation
            PaymentLog::create([
                'order_id' => $order->id,
                'event_type' => 'manual_check',
                'payload' => json_encode([
                    'action' => 'create_payment',
                    'snap_token' => $snapToken,
                    'order_number' => $order->order_number,
                    'gross_amount' => $transactionDetails['gross_amount'],
                    'timestamp' => now()->toIso8601String(),
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully',
                'data' => [
                    'snap_token' => $snapToken,
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                    ],
                    'payment' => [
                        'id' => $payment->id,
                        'payment_status' => $payment->payment_status,
                        'expired_at' => $payment->expired_at,
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Payment Creation Error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ], 500);
        }
    }

 /**
     * Handle Midtrans notification webhook
     */
    #[OA\Post(
        path: "/payment/notification",
        tags: ["Payment"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "order_id", type: "string"),
                    new OA\Property(property: "status_code", type: "string"),
                    new OA\Property(property: "gross_amount", type: "string"),
                    new OA\Property(property: "transaction_status", type: "string"),
                    new OA\Property(property: "fraud_status", type: "string"),
                    new OA\Property(property: "payment_type", type: "string"),
                    new OA\Property(property: "transaction_id", type: "string"),
                    new OA\Property(property: "signature_key", type: "string"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Notification processed"),
            new OA\Response(response: 403, description: "Invalid signature"),
        ]
    )]
    public function notification(Request $request, MidtransSignatureVerifier $verifier)
    {
        DB::beginTransaction();

        try {
            // ============================================
            // VERIFY SIGNATURE - dari $request langsung, SEBELUM
            // manggil apapun yang nyentuh network (Notification/Transaction::status
            // bikin outbound call ke Midtrans, jangan sampai kepanggil buat payload sampah)
            // ============================================
            $signatureData = [
                'order_id' => $request->input('order_id'),
                'status_code' => $request->input('status_code'),
                'gross_amount' => $request->input('gross_amount'),
                'signature_key' => $request->input('signature_key'),
            ];

            if (!$verifier->isValid($signatureData, config('midtrans.server_key'))) {
                DB::rollBack();

                Log::warning('Midtrans Notification: Invalid Signature', [
                    'order_id' => $signatureData['order_id'],
                    'status_code' => $signatureData['status_code'],
                    'gross_amount' => $signatureData['gross_amount'],
                    'ip' => $request->ip(),
                ]);

                // Log sebagai bukti percobaan pemalsuan notifikasi
                try {
                    PaymentLog::create([
                        'order_id' => null,
                        'event_type' => 'error',
                        'payload' => json_encode([
                            'reason' => 'invalid_signature',
                            'order_id' => $signatureData['order_id'],
                            'ip' => $request->ip(),
                            'timestamp' => now()->toIso8601String(),
                        ]),
                    ]);
                } catch (\Exception $logError) {
                    Log::error('Failed to log invalid signature attempt', ['error' => $logError->getMessage()]);
                }

                return response()->json([
                    'message' => 'Invalid signature'
                ], 403);
            }
            // ============================================

            // Setup Midtrans Configuration
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');

            // Signature sudah tervalidasi. Ambil status resmi dari Midtrans
            // (bukan sekadar percaya body request) sebagai konfirmasi kedua.
            $notif = new \Midtrans\Notification();

            $orderNumber = $notif->order_id;
            $transactionStatus = $notif->transaction_status;
            $fraudStatus = $notif->fraud_status ?? null;
            $paymentType = $notif->payment_type;
            $transactionId = $notif->transaction_id;

            Log::info('Midtrans Notification Received', [
                'order_number' => $orderNumber,
                'transaction_id' => $transactionId,
                'transaction_status' => $transactionStatus,
                'payment_type' => $paymentType,
                'fraud_status' => $fraudStatus,
            ]);

            // Cari order berdasarkan order_number
            $order = Order::where('order_number', $orderNumber)->first();

            if (!$order) {
                Log::error('Order not found in notification', [
                    'order_number' => $orderNumber,
                ]);

                return response()->json([
                    'message' => 'Order not found'
                ], 404);
            }

            // Log notification
            PaymentLog::create([
                'order_id' => $order->id,
                'event_type' => 'notification',
                'payload' => json_encode([
                    'order_number' => $orderNumber,
                    'transaction_id' => $transactionId,
                    'transaction_status' => $transactionStatus,
                    'payment_type' => $paymentType,
                    'fraud_status' => $fraudStatus,
                    'raw_notification' => $request->all(),
                    'timestamp' => now()->toIso8601String(),
                ]),
            ]);

            $payment = Payment::where('order_id', $order->id)->first();

            if (!$payment) {
                Log::error('Payment not found in notification', [
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                ]);

                return response()->json([
                    'message' => 'Payment not found'
                ], 404);
            }

            // ============================================
            // IDEMPOTENCY GUARD - cegah notif duplikat/replay
            // memproses ulang payment yang statusnya sudah final
            // ============================================
            $finalStatuses = ['settlement', 'failure', 'expire', 'cancel'];

            if (in_array($payment->payment_status, $finalStatuses)) {
                Log::info('Notification skipped: payment already in final status', [
                    'order_number' => $orderNumber,
                    'current_status' => $payment->payment_status,
                    'incoming_status' => $transactionStatus,
                    'transaction_id' => $transactionId,
                ]);

                DB::commit();

                // Balikin 200 supaya Midtrans berhenti retry - notif ini sudah "diterima",
                // cuma sengaja tidak diproses ulang.
                return response()->json([
                    'message' => 'Notification already processed, status unchanged'
                ], 200);
            }
            // ============================================

            // Update payment data
            $payment->transaction_id = $transactionId;
            $payment->payment_type = $paymentType;
            $payment->midtrans_response = json_encode($notif);

            // Handle transaction status
            if ($transactionStatus == 'capture') {
                if ($fraudStatus == 'accept') {
                    $payment->markAsSettlement();
                    $order->markAsPaid();
                    Log::info('Payment captured and accepted', ['order_number' => $orderNumber]);
                }
            } elseif ($transactionStatus == 'settlement') {
                $payment->markAsSettlement();
                $order->markAsPaid();
                Log::info('Payment settled', ['order_number' => $orderNumber]);
            } elseif ($transactionStatus == 'pending') {
                $payment->payment_status = 'pending';
                $order->status = 'pending';
                Log::info('Payment pending', ['order_number' => $orderNumber]);
            } elseif ($transactionStatus == 'deny') {
                $payment->payment_status = 'deny';
                $order->markAsFailed();
                Log::info('Payment denied', ['order_number' => $orderNumber]);
            } elseif ($transactionStatus == 'expire') {
                $payment->markAsExpired();
                $order->markAsExpired();
                Log::info('Payment expired', ['order_number' => $orderNumber]);
            } elseif ($transactionStatus == 'cancel') {
                $payment->payment_status = 'cancel';
                $order->status = 'cancelled';
                Log::info('Payment cancelled', ['order_number' => $orderNumber]);
            }

            $payment->save();
            $order->save();

            DB::commit();

            return response()->json([
                'message' => 'OK'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Notification Processing Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            // Log error
            try {
                PaymentLog::create([
                    'order_id' => null,
                    'event_type' => 'error',
                    'payload' => json_encode([
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'request' => $request->all(),
                        'timestamp' => now()->toIso8601String(),
                    ]),
                ]);
            } catch (\Exception $logError) {
                Log::error('Failed to log error', ['error' => $logError->getMessage()]);
            }

            return response()->json([
                'message' => 'Error processing notification'
            ], 500);
        }
    }

    /**
     * Check payment status
     */
   #[OA\Get(
        path: "/payment/{orderId}/status",
        tags: ["Payment"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "orderId", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [new OA\Response(response: 200, description: "Payment status")]
    )]
    public function status(Request $request, string $orderId)
    {
        try {
            $order = Order::with(['payment', 'items.product'])
                ->where('user_id', $request->user()->id)
                ->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            $payment = $order->payment;

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            // Check status from Midtrans jika sudah ada transaction_id
            if ($payment->transaction_id) {
                try {
                    \Midtrans\Config::$serverKey = config('midtrans.server_key');
                    \Midtrans\Config::$isProduction = config('midtrans.is_production');

                    $status = \Midtrans\Transaction::status($payment->transaction_id);

                    Log::info('Payment Status Checked', [
                        'transaction_id' => $payment->transaction_id,
                        'status' => $status->transaction_status,
                    ]);

                    // Update payment status dari Midtrans
                    $payment->payment_status = $status->transaction_status;
                    $payment->midtrans_response = json_encode($status);
                    $payment->save();

                    // Log status check
                    PaymentLog::create([
                        'order_id' => $order->id,
                        'event_type' => 'manual_check',
                        'payload' => json_encode([
                            'action' => 'status_check',
                            'transaction_id' => $payment->transaction_id,
                            'status' => $status->transaction_status,
                            'timestamp' => now()->toIso8601String(),
                        ]),
                    ]);

                } catch (\Exception $e) {
                    Log::warning('Midtrans Status Check Failed', [
                        'transaction_id' => $payment->transaction_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                        'created_at' => $order->created_at,
                    ],
                    'payment' => [
                        'id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'payment_type' => $payment->payment_type,
                        'payment_status' => $payment->payment_status,
                        'gross_amount' => $payment->gross_amount,
                        'snap_token' => $payment->snap_token,
                        'paid_at' => $payment->paid_at,
                        'expired_at' => $payment->expired_at,
                    ],
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'price' => $item->product_price,
                            'subtotal' => $item->subtotal,
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Status Check Error', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
