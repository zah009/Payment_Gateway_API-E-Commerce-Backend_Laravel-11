<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST MIDTRANS CONFIG ===\n\n";

// Load config
$serverKey = config('midtrans.server_key');
$clientKey = config('midtrans.client_key');
$isProduction = config('midtrans.is_production');

echo "Server Key: " . ($serverKey ? "✅ SET" : "❌ NOT SET") . "\n";
echo "Client Key: " . ($clientKey ? "✅ SET" : "❌ NOT SET") . "\n";
echo "Environment: " . ($isProduction ? "Production" : "Sandbox") . "\n\n";

if (!$serverKey || !$clientKey) {
    echo "❌ Midtrans keys belum diset di .env!\n";
    exit(1);
}

// Test Midtrans API
try {
    \Midtrans\Config::$serverKey = $serverKey;
    \Midtrans\Config::$isProduction = $isProduction;

    // Test ping (simple check)
    echo "Testing Midtrans connection...\n";

    // Create dummy transaction
    $params = [
        'transaction_details' => [
            'order_id' => 'TEST-' . time(),
            'gross_amount' => 10000,
        ],
        'customer_details' => [
            'first_name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '081234567890',
        ],
    ];

    $snapToken = \Midtrans\Snap::getSnapToken($params);

    echo "✅ Midtrans connection SUCCESS!\n";
    echo "Snap Token: " . substr($snapToken, 0, 20) . "...\n";

} catch (\Exception $e) {
    echo "❌ Midtrans connection FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
}
