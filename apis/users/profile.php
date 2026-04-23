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
    $newReferralApplied = false;

    // Validate referral code ownership before applying it
    if ($invitationCode !== '') {
        $ownerStmt = $pdo->prepare("SELECT id FROM customers WHERE referral_code = :code LIMIT 1");
        $ownerStmt->execute([':code' => $invitationCode]);
        $refOwnerId = (int) ($ownerStmt->fetchColumn() ?: 0);
        if ($refOwnerId <= 0 || $refOwnerId === (int) $customerId) {
            $invitationCode = '';
        }
    }

    $currentRefStmt = $pdo->prepare("SELECT referred_by FROM customers WHERE id = :id LIMIT 1");
    $currentRefStmt->execute([':id' => $customerId]);
    $currentReferredBy = (string) ($currentRefStmt->fetchColumn() ?: '');
    $newReferralApplied = ($currentReferredBy === '' && $invitationCode !== '');

    $stmt = $pdo->prepare("
        UPDATE customers
        SET name             = COALESCE(NULLIF(:name, ''), name),
            phone            = COALESCE(NULLIF(:phone, ''), phone),
            segment          = :segment,
            governorate      = CASE WHEN :gov != '' THEN :gov_val ELSE governorate END,
            governorate_id   = COALESCE(:gov_id, governorate_id),
            city             = CASE WHEN :city != '' THEN :city_val ELSE city END,
            city_id          = COALESCE(:city_id, city_id),
            address_detail   = CASE WHEN :addr != '' THEN :addr_val ELSE address_detail END,
            lat              = COALESCE(:lat, lat),
            lng              = COALESCE(:lng, lng),
            referred_by      = CASE WHEN referred_by IS NULL AND :ref_code != '' THEN :ref_code2 ELSE referred_by END,
            profile_complete = 1
        WHERE id = :id
    ");
    $stmt->execute([
        ':name'      => $name,
        ':phone'     => $phone,
        ':segment'   => $segment,
        ':gov'       => $govName,
        ':gov_val'   => $govName,
        ':gov_id'    => $govId,
        ':city'      => $city,
        ':city_val'  => $city,
        ':city_id'   => $cityId,
        ':addr'      => $address,
        ':addr_val'  => $address,
        ':lat'       => $lat,
        ':lng'       => $lng,
        ':ref_code'  => $invitationCode,
        ':ref_code2' => $invitationCode !== '' ? $invitationCode : null,
        ':id'        => $customerId,
    ]);

    // Refresh session name if updated
    if ($name !== '') $_SESSION['customer_name'] = $name;

    // Add referral reward once when code is used for the first time
    if ($newReferralApplied) {
        try {
            $refOwnerStmt = $pdo->prepare("SELECT id FROM customers WHERE referral_code = :code LIMIT 1");
            $refOwnerStmt->execute([':code' => $invitationCode]);
            $referrerId = (int) ($refOwnerStmt->fetchColumn() ?: 0);

            if ($referrerId > 0 && $referrerId !== (int) $customerId) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS wallets (
                        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        customer_id BIGINT UNSIGNED NOT NULL,
                        balance     DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
                        created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY uk_wallets_customer (customer_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS wallet_transactions (
                        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        customer_id BIGINT UNSIGNED NOT NULL,
                        amount      DECIMAL(12,2)   NOT NULL,
                        type        ENUM('credit','debit') NOT NULL,
                        reason      VARCHAR(100)    NOT NULL,
                        ref_id      BIGINT UNSIGNED DEFAULT NULL,
                        created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        KEY idx_wt_customer (customer_id),
                        KEY idx_wt_created (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                $dupStmt = $pdo->prepare("
                    SELECT id
                    FROM wallet_transactions
                    WHERE customer_id = :cid
                      AND reason = 'referral_bonus'
                      AND ref_id = :ref_id
                    LIMIT 1
                ");
                $dupStmt->execute([':cid' => $referrerId, ':ref_id' => $customerId]);

                if (!$dupStmt->fetchColumn()) {
                    $pdo->prepare("INSERT IGNORE INTO wallets (customer_id, balance) VALUES (:cid, 0.00)")
                        ->execute([':cid' => $referrerId]);

                    $pdo->prepare("UPDATE wallets SET balance = balance + 15.00 WHERE customer_id = :cid")
                        ->execute([':cid' => $referrerId]);

                    $pdo->prepare("
                        INSERT INTO wallet_transactions (customer_id, amount, type, reason, ref_id)
                        VALUES (:cid, 15.00, 'credit', 'referral_bonus', :ref_id)
                    ")->execute([':cid' => $referrerId, ':ref_id' => $customerId]);
                }
            }
        } catch (Throwable $e) {
            // Keep profile save successful even if reward logging fails
        }
    }

} catch (Throwable $e) {
    api_error('خطأ في حفظ البيانات', 500);
}

api_ok(['profile_complete' => true]);
