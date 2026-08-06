<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================================
    // POST /orders
    // ==========================================================

    /** @test */
    public function create_order_ditolak_tanpa_login(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function create_order_berhasil_dan_mengurangi_stock_produk(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 50000, 'stock' => 10]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => ['total_amount' => '150000.00'],
            ]);

        $this->assertSame(7, $product->fresh()->stock);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    }

    /** @test */
    public function create_order_ditolak_kalau_stock_tidak_cukup_dan_stock_tidak_berubah(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 2]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5],
                ],
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);

        // Stock tidak boleh berubah sama sekali kalau order ditolak
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function create_order_ditolak_kalau_salah_satu_produk_tidak_aktif(): void
    {
        $user = User::factory()->create();
        $productAktif = Product::factory()->create(['stock' => 10]);
        $productNonaktif = Product::factory()->inactive()->create(['stock' => 10]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $productAktif->id, 'quantity' => 1],
                    ['product_id' => $productNonaktif->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(400);
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Ini test paling penting untuk fix race-condition: kalau item pertama
     * berhasil dikunci & valid tapi item KEDUA ternyata stoknya kurang,
     * seluruh transaksi harus rollback - termasuk stock item pertama yang
     * sudah sempat "dikurangi" secara logis tidak boleh benar-benar
     * ter-commit ke database.
     */
    /** @test */
    public function create_order_rollback_stock_kalau_salah_satu_item_gagal_di_tengah_transaksi(): void
    {
        $user = User::factory()->create();
        $productCukup = Product::factory()->create(['stock' => 10]);
        $productKurang = Product::factory()->create(['stock' => 1]);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $productCukup->id, 'quantity' => 2],
                    ['product_id' => $productKurang->id, 'quantity' => 5], // stok kurang
                ],
            ]);

        $response->assertStatus(400);

        // productCukup TIDAK boleh ikut berkurang walau item-nya "valid" -
        // karena order-nya batal seluruhnya (all-or-nothing).
        $this->assertSame(10, $productCukup->fresh()->stock);
        $this->assertSame(1, $productKurang->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    /** @test */
    public function create_order_404_kalau_product_id_tidak_ada(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => '00000000-0000-0000-0000-000000000000', 'quantity' => 1],
                ],
            ]);

        // Divalidasi lebih dulu oleh OrderRequest (exists:products,id) -> 422,
        // bukan sampai ke controller.
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    /** @test */
    public function create_order_gagal_validasi_kalau_items_kosong(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/orders', ['items' => []]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    // ==========================================================
    // POST /orders/{id}/cancel
    // ==========================================================

    /** @test */
    public function cancel_order_mengembalikan_stock_produk(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->for($user)->create(['status' => 'pending']);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 3,
        ]);
        $product->decreaseStock(3); // simulasikan stock yang sudah terpotong saat order dibuat

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    /** @test */
    public function cancel_order_ditolak_kalau_order_bukan_milik_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $order = Order::factory()->for($userA)->create(['status' => 'pending']);

        $response = $this->actingAs($userB, 'api')
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(404);
    }

    /** @test */
    public function cancel_order_ditolak_kalau_status_sudah_bukan_pending(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->paid()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(400)
            ->assertJson(['success' => false, 'message' => 'Only pending orders can be cancelled']);
    }
}