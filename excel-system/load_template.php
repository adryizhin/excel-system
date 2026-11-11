<?php
require_once __DIR__ . "/config/database.php";

header("Content-Type: application/json");

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid template ID."]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM templates WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$template = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$template) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Template not found."]);
    exit;
}

$storedHeaders = json_decode($template["headers_json"], true) ?: [];
$headers = $storedHeaders['headers'] ?? $storedHeaders;
$types = $storedHeaders['column_types'] ?? array_fill(0, count($headers), 'text');

echo json_encode([
    "success" => true,
    "template" => [
        "id" => (int)$template["id"],
        "name" => $template["name"],
        "column_count" => (int)$template["column_count"],
        "row_count" => (int)$template["row_count"],
        "headers" => $headers,
        "column_widths" => $storedHeaders['column_widths'] ?? [],
        "formats" => $storedHeaders['formats'] ?? [],
        "column_types" => $types,
        "column_options" => $storedHeaders['column_options'] ?? array_fill(0, count($headers), []),
        "data" => json_decode($template["data_json"], true) ?: []
    ]
]);
?>
