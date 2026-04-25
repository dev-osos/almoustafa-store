<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['GET']);
require_once __DIR__ . '/../customer_auth.php';

try {
    $pdo = api_pdo();

    $stmt = $pdo->prepare("
        SELECT
            id,
            order_number,
            customer_name,
            customer_phone,
            governorate,
            city,
            address_detail,
            full_address,
            note,
            status,
            items_json,
            subtotal,
            shipping,
            wallet_discount,
            total,
            created_at,
            updated_at
        FROM customer_orders
        WHERE customer_id = :customer_id
        ORDER BY created_at DESC, id DESC
        LIMIT 200
    ");
    $stmt->execute([':customer_id' => $customerId]);

    $orders = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items = json_decode((string) ($row['items_json'] ?? '[]'), true);
        if (!is_array($items)) {
            $items = [];
        }

        $normalizedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            $qty = (int) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            if ($name === '' || $qty <= 0 || $price < 0) {
                continue;
            }
            $normalizedItems[] = [
                'name' => $name,
                'qty' => $qty,
                'price' => $price,
                'weight' => trim((string) ($item['weight'] ?? '')),
                'img' => trim((string) ($item['img'] ?? $item['image_url'] ?? '')),
                'image' => trim((string) ($item['image'] ?? $item['img'] ?? $item['image_url'] ?? '')),
                'image_url' => trim((string) ($item['image_url'] ?? $item['img'] ?? $item['image'] ?? '')),
                'category' => trim((string) ($item['category'] ?? '')),
            ];
        }

        $address = trim((string) ($row['full_address'] ?? ''));
        if ($address === '') {
            $parts = array_filter([
                trim((string) ($row['governorate'] ?? '')),
                trim((string) ($row['city'] ?? '')),
                trim((string) ($row['address_detail'] ?? '')),
            ], static fn($v) => $v !== '');
            $address = implode('، ', $parts);
        }

        $orders[] = [
            'db_id' => (int) ($row['id'] ?? 0),
            'id' => (string) ($row['order_number'] ?? ''),
            'date' => substr((string) ($row['created_at'] ?? ''), 0, 10),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'address' => $address,
            'products' => $normalizedItems,
            'subtotal' => (float) ($row['subtotal'] ?? 0),
            'shipping' => (float) ($row['shipping'] ?? 0),
            'discount' => (float) ($row['wallet_discount'] ?? 0),
            'total' => (float) ($row['total'] ?? 0),
            'customer_name' => (string) ($row['customer_name'] ?? ''),
            'customer_phone' => (string) ($row['customer_phone'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
        ];
    }

    api_ok([
        'orders' => $orders,
        'count' => count($orders),
    ]);
} catch (Throwable $e) {
    api_error('تعذر جلب الطلبات حالياً', 500, [
        'debug' => $e->getMessage(),
    ]);
}
