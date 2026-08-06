<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    /**
     * Get all orders for authenticated user
     */
    public function index(Request $request)
    {
        $query = Order::with(['items.product', 'payment'])
            ->where('user_id', $request->user()->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $orders = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get single order detail
     */
    public function show(Request $request, string $id)
    {
        $order = Order::with(['items.product', 'payment'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Create new order
     */
    #[OA\Post(
        path: "/orders",
        tags: ["Orders"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["items"],
                properties: [
                    new OA\Property(
                        property: "items",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "product_id", type: "string", example: "5d297491-d6a8-4911-9690-0c335d9b923b"),
                                new OA\Property(property: "quantity", type: "integer", example: 1),
                            ]
                        )
                    ),
                    new OA\Property(property: "notes", type: "string", example: "test order"),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: "Order created")]
    )]
    public function store(OrderRequest $request)
    {
        DB::beginTransaction();

        try {
            $totalAmount = 0;
            $orderItemsData = [];
            $lockedProducts = [];

            // Validate stock & calculate total
            foreach ($request->items as $item) {
                // lockForUpdate() mengunci baris produk ini sampai transaksi commit/rollback,
                // supaya request checkout lain yang concurrent untuk produk yang sama harus
                // antre - mencegah dua request sama-sama lolos pengecekan hasStock() untuk
                // stok terakhir (race condition / oversold).
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Product {$item['product_id']} not found",
                    ], 404);
                }

                if (!$product->is_active) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Product {$product->name} is not available",
                    ], 400);
                }

                if (!$product->hasStock($item['quantity'])) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$product->name}. Available: {$product->stock}",
                    ], 400);
                }

                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ];

                $lockedProducts[$product->id] = $product;
            }

            // Create order
            $order = Order::create([
                'user_id' => $request->user()->id,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            // Create order items & decrease stock (produk sudah dikunci di atas)
            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);

                $product = $lockedProducts[$itemData['product_id']];

                // decreaseStock() atomic (WHERE stock >= qty). Karena baris sudah
                // di-lock sejak lockForUpdate() di atas, ini seharusnya selalu berhasil -
                // tapi tetap dicek eksplisit sebagai pengaman kedua (defense in depth),
                // alih-alih diam-diam mengizinkan stok jadi minus.
                if (!$product->decreaseStock($itemData['quantity'])) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$product->name}",
                    ], 400);
                }
            }

            DB::commit();

            // Load relationships
            $order->load(['items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Order Creation Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Cancel order (only if status is pending)
     */
    public function cancel(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $order = Order::where('user_id', $request->user()->id)
                ->find($id);

            if (!$order) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found',
                ], 404);
            }

            if (!$order->isPending()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending orders can be cancelled',
                ], 400);
            }

            // Return stock (lockForUpdate juga di sini supaya konsisten dengan store(),
            // walau risiko race condition-nya jauh lebih kecil karena ini cuma increment)
            foreach ($order->items as $item) {
                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                if ($product) {
                    $product->increaseStock($item->quantity);
                }
            }

            // Cancel order
            $order->cancel();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Order Cancel Error', [
                'order_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }
}