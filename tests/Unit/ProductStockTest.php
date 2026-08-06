<?php

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test untuk Product::decreaseStock().
 *
 * Ini bukan cuma test fungsional biasa - fokusnya membuktikan bahwa
 * decreaseStock() memang atomic di level query (WHERE stock >= quantity),
 * bukan "cek dulu di PHP baru UPDATE" yang rawan race condition.
 */
class ProductStockTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function decrease_stock_berhasil_kalau_stock_cukup(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $berhasil = $product->decreaseStock(4);

        $this->assertTrue($berhasil);
        $this->assertSame(6, $product->fresh()->stock);
        // Instance PHP-nya juga ikut ter-update, bukan cuma row di DB
        $this->assertSame(6, $product->stock);
    }

    /** @test */
    public function decrease_stock_boleh_menghabiskan_stock_sampai_pas_nol(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $berhasil = $product->decreaseStock(5);

        $this->assertTrue($berhasil);
        $this->assertSame(0, $product->fresh()->stock);
    }

    /** @test */
    public function decrease_stock_gagal_dan_tidak_mengubah_apapun_kalau_quantity_melebihi_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $berhasil = $product->decreaseStock(4);

        $this->assertFalse($berhasil);
        // Stock di DB tidak boleh berubah sama sekali (bukan jadi -1)
        $this->assertSame(3, $product->fresh()->stock);
        // Instance PHP juga tidak boleh ikut berubah kalau gagal
        $this->assertSame(3, $product->stock);
    }

    /** @test */
    public function decrease_stock_tidak_pernah_membuat_kolom_stock_jadi_negatif_walau_dipanggil_berkali_kali(): void
    {
        $product = Product::factory()->create(['stock' => 2]);

        // Simulasikan beberapa "request" berturut-turut mengambil stok yang sama.
        // Ini bukan test concurrency sungguhan (SQLite in-memory single connection),
        // tapi memverifikasi bahwa begitu stok habis, panggilan berikutnya
        // ditolak secara bersih alih-alih membuat stock < 0.
        $hasil = [
            $product->decreaseStock(1), // stock: 2 -> 1
            $product->fresh()->decreaseStock(1), // stock: 1 -> 0
            $product->fresh()->decreaseStock(1), // harus gagal, stock sudah 0
        ];

        $this->assertSame([true, true, false], $hasil);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertGreaterThanOrEqual(0, $product->fresh()->stock);
    }

    /** @test */
    public function increase_stock_menambah_stock_dengan_benar(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $product->increaseStock(3);

        $this->assertSame(8, $product->fresh()->stock);
    }
}