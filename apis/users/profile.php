<?php
declare(strict_types=1);

require_once __DIR__ . '/../response.php';
require_once __DIR__ . '/../db.php';

api_response_init();
api_require_method(['POST']);

require_once __DIR__ . '/../customer_auth.php';

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$name    = trim((string) ($body['name']           ?? ''));
$phone   = trim((string) ($body['phone']          ?? ''));
$segment = (string) ($body['segment']             ?? 'consumer');
$govName = trim((string) ($body['governorate']    ?? ''));
$govId   = isset($body['governorate_id']) ? (int) $body['governorate_id'] : null;
$city    = trim((string) ($body['city']           ?? ''));
$cityId  = isset($body['city_id'])    ? (int) $body['city_id']    : null;
$address = trim((string) ($body['address_detail'] ?? ''));
$lat            = isset($body['lat']) && is_numeric($body['lat']) ? (float) $body['lat'] : null;
$lng            = isset($body['lng']) && is_numeric($body['lng']) ? (float) $body['lng'] : null;
$invitationCode = trim((string) ($body['invitation_code'] ?? ''));
if ($invitationCode !== '' && !preg_match('/^\d{6}$/', $invitationCode)) {
    $invitationCode = '';
}

if (!in_array($segment, ['consumer', 'wholesale', 'corporate'], true)) {
    $segment = 'consumer';
}

try {
    $pdo  = api_pdo();
    $stmt = $pdo->prepare("
        UPDATE customers
        SET name             = :name,
            phone            = COALESCE(NULLIF(:phone, ''), phone),
            segment          = :segment,
            governorate      = NULLIF(:gov, ''),
            governorate_id   = :gov_id,
            city             = NULLIF(:city, ''),
            city_id          = :city_id,
            address_detail   = NULLIF(:addr, ''),
            lat              = :lat,
            lng              = :lng,
            referred_by      = CASE WHEN referred_by IS NULL AND :ref_code != '' THEN :ref_code2 ELSE referred_by END,
            profile_complete = 1
        WHERE id = :id
    ");
    $stmt->execute([
        ':name'      => $name    !== '' ? $name    : null,
        ':phone'     => $phone,
        ':segment'   => $segment,
        ':gov'       => $govName,
        ':gov_id'    => $govId,
        ':city'      => $city,
        ':city_id'   => $cityId,
        ':addr'      => $address,
        ':lat'       => $lat,
        ':lng'       => $lng,
        ':ref_code'  => $invitationCode,
        ':ref_code2' => $invitationCode !== '' ? $invitationCode : null,
        ':id'        => $customerId,
    ]);

    // Refresh session name if updated
    if ($name !== '') $_SESSION['customer_name'] = $name;

} catch (Throwable $e) {
    api_error('خطأ في حفظ البيانات', 500);
}

api_ok(['profile_complete' => true]);
