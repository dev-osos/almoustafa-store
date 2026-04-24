<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['POST']);
require_once __DIR__ . '/../customer_auth.php';

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    api_error('بيانات الطلب غير صالحة', 422);
}

$orderNumber = trim((string) ($body['order_id'] ?? ''));
$customerName = trim((string) ($body['name'] ?? ''));
$customerPhone = trim((string) ($body['phone'] ?? ''));
$governorate = trim((string) ($body['governorate'] ?? ''));
$city = trim((string) ($body['city'] ?? ''));
$addressDetail = trim((string) ($body['address_detail'] ?? ''));
$fullAddress = trim((string) ($body['address'] ?? ''));
$note = trim((string) ($body['note'] ?? ''));
$items = $body['products'] ?? [];
$subtotal = (float) ($body['subtotal'] ?? 0);
$shipping = (float) ($body['shipping'] ?? 0);
$walletDiscount = (float) ($body['wallet_discount'] ?? 0);
$total = (float) ($body['total'] ?? 0);

if ($orderNumber === '' || !preg_match('/^[A-Za-z0-9\-]{4,40}$/', $orderNumber)) {
    api_error('رقم الطلب غير صالح', 422);
}
if ($customerName === '' || $customerPhone === '') {
    api_error('بيانات المستلم غير مكتملة', 422);
}
if (!is_array($items) || count($items) === 0) {
    api_error('لا توجد منتجات في الطلب', 422);
}
if ($subtotal < 0 || $shipping < 0 || $walletDiscount < 0 || $total < 0) {
    api_error('قيم الطلب غير صالحة', 422);
}
if (abs(($subtotal + $shipping - $walletDiscount) - $total) > 1.0) {
    api_error('الإجمالي غير متطابق مع تفاصيل الطلب', 422);
}

$normalizedItems = [];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $name = trim((string) ($item['name'] ?? ''));
    $qty = (int) ($item['qty'] ?? 0);
    $price = (float) ($item['price'] ?? 0);
    $weight = trim((string) ($item['weight'] ?? ''));
    $img = trim((string) ($item['img'] ?? $item['image_url'] ?? ''));
    $category = trim((string) ($item['category'] ?? ''));
    if ($name === '' || $qty <= 0 || $price < 0) {
        continue;
    }
    $normalizedItems[] = [
        'name' => $name,
        'qty' => $qty,
        'price' => $price,
        'weight' => $weight,
        'img' => $img,
        'category' => $category,
    ];
}

if (count($normalizedItems) === 0) {
    api_error('تفاصيل المنتجات غير صالحة', 422);
}

$itemsJson = json_encode($normalizedItems, JSON_UNESCAPED_UNICODE);
if (!is_string($itemsJson) || $itemsJson === '') {
    api_error('تعذر تجهيز بيانات المنتجات', 422);
}

try {
    $pdo = api_pdo();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customer_orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            order_number VARCHAR(40) NOT NULL,
            customer_name VARCHAR(120) NOT NULL,
            customer_phone VARCHAR(25) NOT NULL,
            governorate VARCHAR(120) DEFAULT NULL,
            city VARCHAR(120) DEFAULT NULL,
            address_detail VARCHAR(255) DEFAULT NULL,
            full_address VARCHAR(500) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            items_json LONGTEXT NOT NULL,
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            shipping DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            wallet_discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_customer_orders_customer (customer_id),
            KEY idx_customer_orders_number (order_number),
            KEY idx_customer_orders_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_wallets_customer (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            type ENUM('credit','debit') NOT NULL,
            reason VARCHAR(100) NOT NULL,
            ref_id BIGINT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_wt_customer (customer_id),
            KEY idx_wt_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->beginTransaction();

    $pdo->prepare("INSERT IGNORE INTO wallets (customer_id, balance) VALUES (:cid, 0.00)")
        ->execute([':cid' => $customerId]);

    $walletStmt = $pdo->prepare("SELECT balance FROM wallets WHERE customer_id = :cid LIMIT 1 FOR UPDATE");
    $walletStmt->execute([':cid' => $customerId]);
    $currentBalance = (float) ($walletStmt->fetchColumn() ?: 0);

    if ($walletDiscount > 0 && $walletDiscount > $currentBalance) {
        $pdo->rollBack();
        api_error('رصيد المحفظة غير كافٍ', 409, ['wallet_balance' => $currentBalance]);
    }

    $insertOrder = $pdo->prepare("
        INSERT INTO customer_orders
            (customer_id, order_number, customer_name, customer_phone, governorate, city, address_detail, full_address, note, status, items_json, subtotal, shipping, wallet_discount, total)
        VALUES
            (:customer_id, :order_number, :customer_name, :customer_phone, :governorate, :city, :address_detail, :full_address, :note, 'pending', :items_json, :subtotal, :shipping, :wallet_discount, :total)
    ");
    $insertOrder->execute([
        ':customer_id' => $customerId,
        ':order_number' => $orderNumber,
        ':customer_name' => $customerName,
        ':customer_phone' => $customerPhone,
        ':governorate' => $governorate !== '' ? $governorate : null,
        ':city' => $city !== '' ? $city : null,
        ':address_detail' => $addressDetail !== '' ? $addressDetail : null,
        ':full_address' => $fullAddress !== '' ? $fullAddress : null,
        ':note' => $note !== '' ? $note : null,
        ':items_json' => $itemsJson,
        ':subtotal' => round($subtotal, 2),
        ':shipping' => round($shipping, 2),
        ':wallet_discount' => round($walletDiscount, 2),
        ':total' => round($total, 2),
    ]);
    $dbOrderId = (int) $pdo->lastInsertId();

    if ($walletDiscount > 0) {
        $updateWallet = $pdo->prepare("
            UPDATE wallets
            SET balance = balance - :amount_set
            WHERE customer_id = :cid AND balance >= :amount_check
        ");
        $updateWallet->execute([
            ':amount_set' => round($walletDiscount, 2),
            ':amount_check' => round($walletDiscount, 2),
            ':cid' => $customerId,
        ]);
        if ($updateWallet->rowCount() < 1) {
            $pdo->rollBack();
            api_error('تعذر خصم رصيد المحفظة', 409);
        }

        $pdo->prepare("
            INSERT INTO wallet_transactions (customer_id, amount, type, reason, ref_id)
            VALUES (:cid, :amount, 'debit', 'order_payment', :ref_id)
        ")->execute([
            ':cid' => $customerId,
            ':amount' => round($walletDiscount, 2),
            ':ref_id' => $dbOrderId,
        ]);
    }

    $walletStmt->execute([':cid' => $customerId]);
    $newBalance = (float) ($walletStmt->fetchColumn() ?: 0);

    $pdo->commit();

    api_ok([
        'order_db_id' => $dbOrderId,
        'order_id' => $orderNumber,
        'wallet_balance' => $newBalance,
    ], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_error('حدث خطأ أثناء حفظ الطلب', 500, [
        'debug' => $e->getMessage(),
    ]);
}
