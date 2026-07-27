<?php

namespace App\Models;
use App\Services\MidtransSignatureVerifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'event_type',
        'payload',
    ];

    /**
     * Relasi: PaymentLog belongs to Order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope: Filter by event type
     */
    public function scopeEventType($query, string $type)
    {
        return $query->where('event_type', $type);
    }
}
