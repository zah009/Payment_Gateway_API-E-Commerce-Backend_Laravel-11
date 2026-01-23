<?php

echo "=== TEST KONEKSI POSTGRESQL ===\n\n";

echo "1. PostgreSQL Extension: ";
if (extension_loaded('pdo_pgsql')) {
    echo "✅ INSTALLED\n";
} else {
    echo "❌ NOT INSTALLED\n";
    exit(1);
}

echo "\n2. Test koneksi ke Docker PostgreSQL (port 5433)...\n";

try {
    $pdo = new PDO(
        "pgsql:host=127.0.0.1;port=5433;dbname=payment_db",  // ← PORT 5433
        "payment_user",
        "payment_secret",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "   ✅ KONEKSI BERHASIL!\n";
    echo "   Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";

    $stmt = $pdo->query("SELECT current_user");
    $user = $stmt->fetchColumn();
    echo "   Current User: $user\n";

} catch (PDOException $e) {
    echo "   ❌ KONEKSI GAGAL!\n";
    echo "   Error: " . $e->getMessage() . "\n";
}
