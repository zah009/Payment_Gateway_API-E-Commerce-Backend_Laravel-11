<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function create_payment_ditolak_tanpa_login(): void
    {
        $order = Order::factory()->create();

        $response = $this->postJson("/api/payment/{$order->id}");

        $response->assertStatus(401);
    }

    /** @test */
    public function create_payment_404_kalau_order_tidak_ditemukan(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    /** @test */
    public function create_payment_404_kalau_order_milik_user_lain(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $orderMilikA = Order::factory()->for($userA)->create();

        $response = $this->actingAs($userB, 'sanctum')
            ->postJson("/api/payment/{$orderMilikA->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function create_payment_ditolak_kalau_order_sudah_tidak_pending(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->paid()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/payment/{$order->id}");

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function create_payment_mengembalikan_snap_token_lama_kalau_sudah_pernah_dibuat(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['status' => 'pending']);
        $payment = Payment::factory()->for($order)->create([
            'snap_token' => 'existing-snap-token-abc123',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/payment/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment already created',
                'data' => ['snap_token' => 'existing-snap-token-abc123'],
            ]);
    }

    /** @test */
    public function status_ditolak_tanpa_login(): void
    {
        $order = Order::factory()->create();

        $response = $this->getJson("/api/payment/{$order->id}/status");

        $response->assertStatus(401);
    }

    /** @test */
    public function status_404_kalau_order_milik_user_lain(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $orderMilikA = Order::factory()->for($userA)->create();
        Payment::factory()->for($orderMilikA)->create();

        $response = $this->actingAs($userB, 'sanctum')
            ->getJson("/api/payment/{$orderMilikA->id}/status");

        $response->assertStatus(404);
    }

    /** @test */
    public function status_404_kalau_payment_belum_dibuat(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/payment/{$order->id}/status");

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Payment not found']);
    }

    /** @test */
    public function status_mengembalikan_data_lokal_tanpa_hit_midtrans_kalau_belum_ada_transaction_id(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        $payment = Payment::factory()->for($order)->create([
            'transaction_id' => null,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/payment/{$order->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'payment' => ['payment_status' => 'pending'],
                ],
            ]);
    }

    /** @test */
    public function notification_ditolak_kalau_signature_tidak_valid(): void
    {
        $order = Order::factory()->create();

        $response = $this->postJson('/api/payment/notification', [
            'order_id' => $order->order_number,
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'transaction_status' => 'settlement',
            'signature_key' => 'signature-palsu-dari-attacker',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Invalid signature']);
    }

    /** @test */
    public function notification_ditolak_kalau_field_wajib_tidak_lengkap(): void
    {
        $response = $this->postJson('/api/payment/notification', [
            'order_id' => 'ORD-TEST',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function notification_dengan_signature_invalid_tidak_mengubah_status_order(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $payment = Payment::factory()->for($order)->create(['payment_status' => 'pending']);

        $this->postJson('/api/payment/notification', [
            'order_id' => $order->order_number,
            'status_code' => '200',
            'gross_amount' => (string) $payment->gross_amount,
            'transaction_status' => 'settlement',
            'signature_key' => 'signature-palsu',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'pending',
        ]);
    }
}