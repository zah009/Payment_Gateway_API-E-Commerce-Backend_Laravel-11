<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'image',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi: Product has many OrderItems
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope: Only active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Products with stock
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Check if product has enough stock
     */
    public function hasStock(int $quantity): bool
    {
        return $this->stock >= $quantity;
    }

    /**
     * Decrease stock secara atomic.
     *
     * Sengaja TIDAK pakai $this->decrement() polos, karena decrement() akan selalu
     * mengeksekusi UPDATE tanpa syarat sisa stok — di bawah request concurrent,
     * itu bisa membuat stock jadi minus (oversold).
     *
     * Di sini kondisi `stock >= quantity` dimasukkan ke klausa WHERE, jadi
     * pengecekan dan pengurangan terjadi dalam satu statement SQL yang atomic
     * di level database. Kalau baris yang ter-affect = 0, artinya stok sudah
     * keburu habis diambil request lain sebelum request ini sampai ke DB.
     *
     * Tetap dipanggil di dalam DB::transaction() + lockForUpdate() di caller
     * supaya pesan error "insufficient stock" akurat dan tidak ada TOCTOU
     * antara pengecekan hasStock() dan keputusan bikin order.
     *
     * @return bool  true kalau stok berhasil dikurangi, false kalau stok tidak cukup.
     */
    public function decreaseStock(int $quantity): bool
    {
        $affected = static::where('id', $this->id)
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity);

        if ($affected > 0) {
            $this->stock -= $quantity;
        }

        return $affected > 0;
    }

    /**
     * Increase stock
     */
    public function increaseStock(int $quantity): void
    {
        $this->increment('stock', $quantity);
    }
}