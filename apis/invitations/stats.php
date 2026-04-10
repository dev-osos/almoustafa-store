<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['GET']);

// Check if user is authenticated admin
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
    api_error('غير مصرح بالوصول', 401);
}

try {
    $pdo = api_pdo();
    
    // Get overall statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_codes,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_codes,
            SUM(usage_count) as total_usage,
            COUNT(DISTINCT c.id) as unique_customers
        FROM invitations i
        LEFT JOIN customers c ON i.code = c.invitation_code
    ");
    $statsStmt->execute();
    $overallStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get detailed invitation codes with usage
    $codesStmt = $pdo->prepare("
        SELECT 
            i.id,
            i.code,
            i.created_at,
            i.expires_at,
            i.is_active,
            i.max_uses,
            i.usage_count,
            i.description,
            COUNT(DISTINCT c.id) as customer_count,
            GROUP_CONCAT(
                CONCAT(c.name, '|', c.phone, '|', c.created_at)
                ORDER BY c.created_at DESC
                SEPARATOR '||'
            ) as customers_data
        FROM invitations i
        LEFT JOIN customers c ON i.code = c.invitation_code
        GROUP BY i.id, i.code, i.created_at, i.expires_at, i.is_active, i.max_uses, i.usage_count, i.description
        ORDER BY i.created_at DESC
    ");
    $codesStmt->execute();
    $codes = $codesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    $formattedCodes = [];
    foreach ($codes as $code) {
        $customers = [];
        if ($code['customers_data']) {
            $customerParts = explode('||', $code['customers_data']);
            foreach ($customerParts as $part) {
                if ($part) {
                    list($name, $phone, $createdAt) = explode('|', $part);
                    $customers[] = [
                        'name' => $name ?: 'غير محدد',
                        'phone' => $phone,
                        'created_at' => $createdAt
                    ];
                }
            }
        }
        
        $formattedCodes[] = [
            'id' => (int) $code['id'],
            'code' => $code['code'],
            'created_at' => $code['created_at'],
            'expires_at' => $code['expires_at'],
            'is_active' => (bool) $code['is_active'],
            'max_uses' => $code['max_uses'] ? (int) $code['max_uses'] : null,
            'usage_count' => (int) $code['usage_count'],
            'customer_count' => (int) $code['customer_count'],
            'description' => $code['description'],
            'customers' => $customers
        ];
    }
    
    api_ok([
        'stats' => [
            'total_codes' => (int) $overallStats['total_codes'],
            'active_codes' => (int) $overallStats['active_codes'],
            'total_usage' => (int) $overallStats['total_usage'],
            'unique_customers' => (int) $overallStats['unique_customers']
        ],
        'codes' => $formattedCodes
    ]);
    
} catch (Throwable $e) {
    error_log('Invitations stats error: ' . $e->getMessage());
    api_error('خطأ في جلب البيانات', 500);
}
