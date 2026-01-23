<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'transaction_id',
        'payment_type',
        'gross_amount',
        'payment_status',
        'midtrans_response',
        'snap_token',
        'paid_at',
        'expired_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'gross_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    /**
     * Relasi: Payment belongs to Order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope: Pending payments
     */
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Scope: Settlement payments
     */
    public function scopeSettlement($query)
    {
        return $query->where('payment_status', 'settlement');
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if payment is success
     */
    public function isSuccess(): bool
    {
        return $this->payment_status === 'settlement';
    }

    /**
     * Mark payment as settlement
     */
    public function markAsSettlement(): void
    {
        $this->update([
            'payment_status' => 'settlement',
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(): void
    {
        $this->update(['payment_status' => 'failure']);
    }

    /**
     * Mark payment as expired
     */
    public function markAsExpired(): void
    {
        $this->update(['payment_status' => 'expire']);
    }
}
