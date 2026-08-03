<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    /**
     * Display a listing of products (PUBLIC)
     */
    #[OA\Get(
        path: "/products",
        tags: ["Products"],
        summary: "Get list of products",
        description: "Public endpoint. Supports filtering by active status, stock availability, and search by name.",
        parameters: [
            new OA\Parameter(
                name: "active",
                in: "query",
                required: false,
                description: "Filter by active status (1 or 0)",
                schema: new OA\Schema(type: "boolean", example: true)
            ),
            new OA\Parameter(
                name: "in_stock",
                in: "query",
                required: false,
                description: "Only show products with stock available",
                schema: new OA\Schema(type: "boolean", example: true)
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                required: false,
                description: "Search product by name",
                schema: new OA\Schema(type: "string", example: "keyboard")
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                required: false,
                description: "Number of items per page",
                schema: new OA\Schema(type: "integer", example: 10)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of products (paginated)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "object"),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request)
    {
        $query = Product::query();

        // Filter by active
        if ($request->has('active')) {
            $query->where('is_active', $request->active);
        }

        // Filter by stock
        if ($request->boolean('in_stock')) {
            $query->inStock();
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'ILIKE', '%' . $request->search . '%');
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Display a single product (PUBLIC)
     */
    #[OA\Get(
        path: "/products/{id}",
        tags: ["Products"],
        summary: "Get product detail",
        description: "Public endpoint. Returns a single product by its ID.",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Product ID (UUID)",
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Product detail",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "object"),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Product not found"),
        ]
    )]
    public function show(string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Store a new product (ADMIN ONLY)
     */
    #[OA\Post(
        path: "/products",
        tags: ["Products"],
        summary: "Create new product (Admin only)",
        description: "Requires Bearer token from an account with role = admin. Customers will get 403 Forbidden.",
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "price", "stock"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Wireless Mouse"),
                    new OA\Property(property: "description", type: "string", example: "Ergonomic wireless mouse with USB receiver"),
                    new OA\Property(property: "price", type: "number", format: "float", example: 150000),
                    new OA\Property(property: "stock", type: "integer", example: 50),
                    new OA\Property(property: "image", type: "string", example: "products/mouse.jpg"),
                    new OA\Property(property: "is_active", type: "boolean", example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Product created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Product created successfully"),
                        new OA\Property(property: "data", type: "object"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized (no/invalid token)"),
            new OA\Response(response: 403, description: "Forbidden. Admin access required."),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Failed to create product"),
        ]
    )]
    public function store(ProductRequest $request)
    {
        try {
            $product = Product::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a product (ADMIN ONLY)
     */
    #[OA\Put(
        path: "/products/{id}",
        tags: ["Products"],
        summary: "Update product (Admin only)",
        description: "Requires Bearer token from an account with role = admin. Customers will get 403 Forbidden.",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Product ID (UUID)",
                schema: new OA\Schema(type: "string")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "price", "stock"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Wireless Mouse (Updated)"),
                    new OA\Property(property: "description", type: "string", example: "Ergonomic wireless mouse with USB receiver"),
                    new OA\Property(property: "price", type: "number", format: "float", example: 135000),
                    new OA\Property(property: "stock", type: "integer", example: 40),
                    new OA\Property(property: "image", type: "string", example: "products/mouse.jpg"),
                    new OA\Property(property: "is_active", type: "boolean", example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Product updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Product updated successfully"),
                        new OA\Property(property: "data", type: "object"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized (no/invalid token)"),
            new OA\Response(response: 403, description: "Forbidden. Admin access required."),
            new OA\Response(response: 404, description: "Product not found"),
            new OA\Response(response: 422, description: "Validation error"),
            new OA\Response(response: 500, description: "Failed to update product"),
        ]
    )]
    public function update(ProductRequest $request, string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        try {
            $product->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a product (ADMIN ONLY)
     */
    #[OA\Delete(
        path: "/products/{id}",
        tags: ["Products"],
        summary: "Delete product (Admin only)",
        description: "Requires Bearer token from an account with role = admin. Customers will get 403 Forbidden.",
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Product ID (UUID)",
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Product deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Product deleted successfully"),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized (no/invalid token)"),
            new OA\Response(response: 403, description: "Forbidden. Admin access required."),
            new OA\Response(response: 404, description: "Product not found"),
            new OA\Response(response: 500, description: "Failed to delete product"),
        ]
    )]
    public function destroy(string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        try {
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}