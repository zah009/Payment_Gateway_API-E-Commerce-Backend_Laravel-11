<?php

namespace Tests\Unit;

use App\Services\MidtransSignatureVerifier;
use PHPUnit\Framework\TestCase;

class MidtransSignatureVerifierTest extends TestCase
{
    private MidtransSignatureVerifier $verifier;

    private string $serverKey = 'SB-Mid-server-test-dummy-key-for-phpunit';

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new MidtransSignatureVerifier();
    }

    private function computeValidSignature(string $orderId, string $statusCode, string $grossAmount): string
    {
        return hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
    }

    /** @test */
    public function signature_valid_diterima(): void
    {
        $orderId = 'ORD-20260101-ABC123';
        $statusCode = '200';
        $grossAmount = '50000.00';

        $data = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $this->computeValidSignature($orderId, $statusCode, $grossAmount),
        ];

        $this->assertTrue($this->verifier->isValid($data, $this->serverKey));
    }

    /** @test */
    public function signature_asal_asalan_ditolak(): void
    {
        $data = [
            'order_id' => 'ORD-20260101-ABC123',
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'signature_key' => 'signature-palsu-asal-tulis',
        ];

        $this->assertFalse($this->verifier->isValid($data, $this->serverKey));
    }

    /** @test */
    public function signature_valid_untuk_order_lain_tidak_bisa_dipakai_ulang(): void
    {
        $signatureUntukOrderA = $this->computeValidSignature('ORD-A', '200', '50000.00');

        $data = [
            'order_id' => 'ORD-B',
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'signature_key' => $signatureUntukOrderA,
        ];

        $this->assertFalse($this->verifier->isValid($data, $this->serverKey));
    }

    /** @test */
    public function gross_amount_yang_dimodifikasi_membuat_signature_tidak_valid(): void
    {
        $signatureAsli = $this->computeValidSignature('ORD-XYZ', '200', '10000.00');

        $data = [
            'order_id' => 'ORD-XYZ',
            'status_code' => '200',
            'gross_amount' => '10000000.00',
            'signature_key' => $signatureAsli,
        ];

        $this->assertFalse($this->verifier->isValid($data, $this->serverKey));
    }

    /** @test */
    public function server_key_yang_salah_membuat_signature_valid_pun_ditolak(): void
    {
        $data = [
            'order_id' => 'ORD-1',
            'status_code' => '200',
            'gross_amount' => '10000.00',
            'signature_key' => $this->computeValidSignature('ORD-1', '200', '10000.00'),
        ];

        $this->assertFalse($this->verifier->isValid($data, 'server-key-yang-salah'));
    }

    /** @test */
    public function field_yang_hilang_ditolak_bukan_error(): void
    {
        $data = [
            'order_id' => 'ORD-1',
            'status_code' => '200',
            'signature_key' => 'sembarang',
        ];

        $this->assertFalse($this->verifier->isValid($data, $this->serverKey));
    }

    /** @test */
    public function payload_kosong_ditolak(): void
    {
        $this->assertFalse($this->verifier->isValid([], $this->serverKey));
    }
}