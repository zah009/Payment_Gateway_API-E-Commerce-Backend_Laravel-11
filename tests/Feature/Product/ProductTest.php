<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================================
    // GET /products (PUBLIC)
    // ==========================================================

    /** @test */
    public function siapapun_bisa_lihat_list_produk_tanpa_login(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function list_produk_bisa_difilter_by_active(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->inactive()->create();

        $response = $this->getJson('/api/products?active=1');

        $response->assertStatus(200);
        $names = collect($response->json('data.data'))->pluck('is_active');
        $this->assertTrue($names->every(fn ($active) => $active === true));
    }

    /** @test */
    public function list_produk_bisa_difilter_by_search(): void
    {
        // ProductController::index() pakai operator "ILIKE" yang cuma didukung Postgres.
        // SQLite (dipakai buat testing) gak punya ILIKE, jadi test ini hanya valid
        // saat DB_CONNECTION testing diarahkan ke pgsql.
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped(
                'ProductController::index() menggunakan operator ILIKE (khusus PostgreSQL). '
                . 'Jalankan test ini dengan DB_CONNECTION=pgsql untuk memverifikasi fitur search.'
            );
        }

        Product::factory()->create(['name' => 'Keyboard Mechanical RGB']);
        Product::factory()->create(['name' => 'Mouse Wireless']);

        $response = $this->getJson('/api/products?search=keyboard');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    // ==========================================================
    // GET /products/{id} (PUBLIC)
    // ==========================================================

    /** @test */
    public function siapapun_bisa_lihat_detail_produk_tanpa_login(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['id' => $product->id],
            ]);
    }

    /** @test */
    public function detail_produk_404_kalau_id_tidak_ditemukan(): void
    {
        $response = $this->getJson('/api/products/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    // ==========================================================
    // POST /products (ADMIN ONLY)
    // ==========================================================

    /** @test */
    public function create_produk_ditolak_tanpa_login(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Produk Baru',
            'price' => 100000,
            'stock' => 10,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function create_produk_ditolak_untuk_role_customer(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer, 'api')
            ->postJson('/api/products', [
                'name' => 'Produk Baru',
                'price' => 100000,
                'stock' => 10,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden. Admin access required.',
            ]);

        $this->assertDatabaseCount('products', 0);
    }

    /** @test */
    public function create_produk_berhasil_untuk_role_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/products', [
                'name' => 'Wireless Mouse',
                'description' => 'Mouse wireless ergonomis',
                'price' => 150000,
                'stock' => 50,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => ['name' => 'Wireless Mouse'],
            ]);

        $this->assertDatabaseHas('products', ['name' => 'Wireless Mouse']);
    }

    /** @test */
    public function create_produk_gagal_validasi_kalau_field_wajib_kosong(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'api')
            ->postJson('/api/products', [
                'description' => 'Tanpa nama, harga, dan stok',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price', 'stock']);
    }

    // ==========================================================
    // PUT /products/{id} (ADMIN ONLY)
    // ==========================================================

    /** @test */
    public function update_produk_ditolak_tanpa_login(): void
    {
        $product = Product::factory()->create();

        $response = $this->putJson("/api/products/{$product->id}", [
            'name' => 'Update Tanpa Login',
            'price' => 1,
            'stock' => 1,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function update_produk_ditolak_untuk_role_customer(): void
    {
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create(['name' => 'Nama Asli']);

        $response = $this->actingAs($customer, 'api')
            ->putJson("/api/products/{$product->id}", [
                'name' => 'Diubah Customer',
                'price' => 1,
                'stock' => 1,
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Nama Asli',
        ]);
    }

    /** @test */
    public function update_produk_berhasil_untuk_role_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'name' => 'Nama Lama',
            'price' => 100000,
            'stock' => 10,
        ]);

        $response = $this->actingAs($admin, 'api')
            ->putJson("/api/products/{$product->id}", [
                'name' => 'Nama Baru',
                'price' => 135000,
                'stock' => 40,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => ['name' => 'Nama Baru'],
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Nama Baru',
            'stock' => 40,
        ]);
    }

    /** @test */
    public function update_produk_404_kalau_id_tidak_ditemukan(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'api')
            ->putJson('/api/products/00000000-0000-0000-0000-000000000000', [
                'name' => 'Tidak Ada',
                'price' => 1,
                'stock' => 1,
            ]);

        $response->assertStatus(404);
    }

    // ==========================================================
    // DELETE /products/{id} (ADMIN ONLY)
    // ==========================================================

    /** @test */
    public function delete_produk_ditolak_tanpa_login(): void
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(401);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /** @test */
    public function delete_produk_ditolak_untuk_role_customer(): void
    {
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($customer, 'api')
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /** @test */
    public function delete_produk_berhasil_untuk_role_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin, 'api')
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /** @test */
    public function delete_produk_404_kalau_id_tidak_ditemukan(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'api')
            ->deleteJson('/api/products/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }
}
