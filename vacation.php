<?php
require_once __DIR__ . '/lib/Vacation.class.php';

$vacation = new Vacation('.env');
$result = $vacation->handle();

header('Content-Type: application/json');
http_response_code($result['status'] ?? 200);
echo json_encode([
    'ok' => $result['ok'] ?? false,
    'active' => $result['active'] ?? false,
    'end_date' => $result['end_date'] ?? null,
    'message' => $result['message'] ?? null,
    'error' => $result['error'] ?? null,
]);
