<?php

namespace App\Services;

class MidtransSignatureVerifier
{
    /**
     * Verifikasi signature notifikasi Midtrans.
     *
     * Formula resmi Midtrans:
     * SHA512(order_id + status_code + gross_amount + ServerKey)
     *
     * Sengaja dipisah jadi class murni (tanpa dependency ke Request,
     * config, atau network) supaya bisa di-unit-test tanpa mocking apapun.
     *
     * @param  array{order_id: ?string, status_code: ?string, gross_amount: ?string, signature_key: ?string}  $data
     */
    public function isValid(array $data, string $serverKey): bool
    {
        if (empty($data['order_id']) || empty($data['status_code']) || !isset($data['gross_amount']) || empty($data['signature_key'])) {
            return false;
        }

        $expected = hash('sha512',
            $data['order_id'] .
            $data['status_code'] .
            $data['gross_amount'] .
            $serverKey
        );

        return hash_equals($expected, (string) $data['signature_key']);
    }
}