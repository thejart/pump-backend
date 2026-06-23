<?php
require_once __DIR__ . '/lib/Vacation.class.php';

$vacation = new Vacation('.env');
$result = $vacation->handle();

// Browser form submissions pass redirect=1 so a refresh won't resubmit the write.
if ($vacation->wantsRedirect()) {
    $location = 'shitshow.php';
    if (!empty($result['flash'])) {
        $location .= '?vacation=' . urlencode($result['flash']);
    }
    header('Location: ' . $location, true, 303);
    exit;
}

header('Content-Type: application/json');
http_response_code($result['status']);
echo json_encode([
    'active' => $result['active'] ?? false,
    'end_date' => $result['end_date'] ?? null,
    'error' => $result['error'] ?? null,
]);
