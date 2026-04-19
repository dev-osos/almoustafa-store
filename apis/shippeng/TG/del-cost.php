<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/response.php';

api_response_init();
api_require_method(['POST']);

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['govId'], $body['cityId'], $body['price'], $body['weight'])) {
    api_error('missing_params', 400);
}

$payload = json_encode([
    'operationName' => 'CalculateShipmentFees',
    'variables'     => [
        'input' => [
            'price'              => (float) $body['price'],
            'recipientSubzoneId' => (int) $body['cityId'],
            'recipientZoneId'    => (int) $body['govId'],
            'serviceId'          => 1,
            'weight'             => (int) $body['weight'],
            'paymentTypeCode'    => 'COLC',
            'priceTypeCode'      => 'INCLD',
            'senderSubzoneId'    => 346,
            'senderZoneId'       => 1,
            'size'               => ['height' => 0, 'length' => 0, 'width' => 0],
        ],
    ],
    'query' => "query CalculateShipmentFees(\$input: CalculateShipmentFeesInput!) {\n  calculateShipmentFees(input: \$input) {\n    amount\n    delivery\n    weight\n    collection\n    total\n    tax\n    post\n    return\n  }\n}",
]);

$ch = curl_init('https://system.telegraphex.com:8443/graphql');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Accept: */*',
        'Authorization: Bearer 245467|m90rxf6dkwYyeku570WIGKSuyhkZr1Kt2ehSUQVLf862e568',
        'Content-Type: application/json',
        'x-app-version: 5.2.2',
        'x-client-name: Mac OS-Safari',
        'x-client-type: WEB',
    ],
    CURLOPT_TIMEOUT        => 15,
]);
$resp = curl_exec($ch);
curl_close($ch);

if (!$resp) {
    api_error('upstream_error', 502);
}

$data = json_decode($resp, true);
$fees = $data['data']['calculateShipmentFees'] ?? null;
if (!is_array($fees)) {
    api_error('invalid_response', 502);
}

api_ok(['delivery' => (float) ($fees['delivery'] ?? 0)]);
