<?php

declare(strict_types=1);

header('Content-Type: application/json');

$modeFile = '/tmp/provider-mode';
$acceptedFile = '/tmp/provider-accepted.json';
$deliveryLog = '/tmp/received-notifications.log';

if (! file_exists($modeFile)) file_put_contents($modeFile, 'normal');
if (! file_exists($acceptedFile)) file_put_contents($acceptedFile, '{}');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method === 'GET' && $path === '/health') {
    jsonResponse(['status' => 'ok', 'mode' => trim(file_get_contents('/tmp/provider-mode'))]);
}

if ($method === 'GET' && $path === '/admin/mode') {
    jsonResponse(['mode' => trim(file_get_contents('/tmp/provider-mode'))]);
}

if ($method === 'POST' && $path === '/admin/reset') {
    file_put_contents($acceptedFile, '{}');
    @unlink($deliveryLog);
    file_put_contents($modeFile, 'normal');
    jsonResponse(['reset' => true, 'mode' => 'normal']);
}

if ($method === 'POST' && $path === '/admin/mode') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $mode = $payload['mode'] ?? null;
    $allowedModes = ['normal', 'slow', 'error', 'duplicate', 'down'];

    if (! in_array($mode, $allowedModes, true)) {
        jsonResponse(['message' => 'Invalid mode.', 'allowed_modes' => $allowedModes], 422);
    }

    file_put_contents($modeFile, $mode);
    jsonResponse(['mode' => $mode]);
}

if ($method === 'POST' && $path === '/notifications') {
    $mode = trim(file_get_contents($modeFile));
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $notificationId = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;

    if (! $notificationId) {
        jsonResponse(['message' => 'Idempotency-Key header is required.'], 422);
    }

    if ($mode === 'down') jsonResponse(['message' => 'Provider unavailable.'], 503);
    if ($mode === 'error') jsonResponse(['message' => 'Simulated provider error.'], 500);
    if ($mode === 'slow') sleep(5);

    $accepted = json_decode(file_get_contents($acceptedFile), true) ?: [];

    if (isset($accepted[$notificationId])) {
        file_put_contents($deliveryLog, json_encode([
            'notification_id' => $notificationId,
            'received_at' => date(DATE_ATOM),
            'deduplicated_retry' => true,
        ]) . PHP_EOL, FILE_APPEND);

        jsonResponse([
            'accepted' => true,
            'notification_id' => $notificationId,
            'deduplicated' => true,
            'mode' => $mode,
        ], 202);
    }

    $accepted[$notificationId] = date(DATE_ATOM);
    file_put_contents($acceptedFile, json_encode($accepted));

    $record = [
        'notification_id' => $notificationId,
        'received_at' => date(DATE_ATOM),
        'payload' => $payload,
    ];
    file_put_contents($deliveryLog, json_encode($record) . PHP_EOL, FILE_APPEND);

    if ($mode === 'duplicate') {
        file_put_contents($deliveryLog, json_encode([
            ...$record,
            'duplicate_delivery' => true,
        ]) . PHP_EOL, FILE_APPEND);
    }

    jsonResponse([
        'accepted' => true,
        'notification_id' => $notificationId,
        'mode' => $mode,
    ], 202);
}

jsonResponse(['message' => 'Not found.'], 404);
