<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

function deleteResponse(int $status, bool $success, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    deleteResponse(405, false, 'POST request required.');
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    deleteResponse(403, false, 'Please refresh the page and try again.');
}

$payload = json_decode(file_get_contents('php://input'), true);
$id = is_array($payload) ? filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
if (!$id) deleteResponse(400, false, 'Invalid template ID.');

try {
    require_once __DIR__ . '/config/database.php';
    $stmt = $conn->prepare('DELETE FROM templates WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    if (!$deleted) deleteResponse(404, false, 'Template not found. Refresh the dashboard.');
    deleteResponse(200, true, 'Template deleted.');
} catch (Throwable $e) {
    error_log('Template deletion failed: ' . $e->getMessage());
    deleteResponse(500, false, 'Unable to delete template. Please try again.');
}
