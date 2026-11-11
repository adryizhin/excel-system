<?php

ob_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/config/database.php";

function response($success, $message, $data = [], $status = 200)
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    response(
        false,
        "Invalid request method.",
        [],
        405
    );
}

$input = file_get_contents("php://input");

if ($input === false || trim($input) === "") {
    response(
        false,
        "No data was received.",
        [],
        400
    );
}

$data = json_decode($input, true);

if (!is_array($data)) {
    response(
        false,
        "Invalid JSON data.",
        [],
        400
    );
}

$fileName = isset($data["file_name"])
    ? trim((string)$data["file_name"])
    : "My Spreadsheet";

if ($fileName === "") {
    $fileName = "My Spreadsheet";
}

$headers = $data["headers"] ?? [];
$rows = $data["data"] ?? [];

if (!is_array($headers)) {
    response(
        false,
        "Invalid column headers.",
        [],
        400
    );
}

if (!is_array($rows)) {
    response(
        false,
        "Invalid spreadsheet rows.",
        [],
        400
    );
}

if (count($headers) === 0) {
    response(
        false,
        "At least one column is required.",
        [],
        400
    );
}

if (count($headers) > 50) {
    response(
        false,
        "Maximum of 50 columns allowed.",
        [],
        400
    );
}

if (count($rows) > 500) {
    response(
        false,
        "Maximum of 500 rows allowed.",
        [],
        400
    );
}

$cleanHeaders = [];

foreach ($headers as $index => $header) {

    if (!is_scalar($header)) {
        $header = "";
    }

    $header = trim((string)$header);

    if ($header === "") {
        $header = "Column " . ($index + 1);
    }

    $cleanHeaders[] = $header;
}

$columnCount = count($cleanHeaders);

$cleanRows = [];

foreach ($rows as $row) {

    if (!is_array($row)) {
        $row = [];
    }

    $cleanRow = [];

    for ($i = 0; $i < $columnCount; $i++) {

        $value = $row[$i] ?? "";

        if (!is_scalar($value)) {
            $value = "";
        }

        $cleanRow[] = (string)$value;
    }

    $cleanRows[] = $cleanRow;
}

require_once __DIR__ . '/column_types.php';
try {
    require_once __DIR__ . '/cell_formats.php';
    $columnWidths = columnWidths($data, $columnCount);
    $formats = cellFormats($data, count($cleanRows), $columnCount);
    $types = columnTypes($data, $columnCount);
    $options = columnOptions($data, $types);
    validateTypedRows($cleanRows, $types, $options);
} catch (InvalidArgumentException $e) {
    response(false, $e->getMessage(), [], 400);
}

$headersJson = json_encode(
    ['headers' => $cleanHeaders, 'column_types' => $types, 'column_options' => $options, 'formats' => $formats, 'column_widths' => $columnWidths],
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_INVALID_UTF8_SUBSTITUTE
);

$rowsJson = json_encode(
    $cleanRows,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_INVALID_UTF8_SUBSTITUTE
);

if ($headersJson === false) {
    response(
        false,
        "Could not encode column headers.",
        [],
        500
    );
}

if ($rowsJson === false) {
    response(
        false,
        "Could not encode spreadsheet data.",
        [],
        500
    );
}

$fileName = mb_substr($fileName, 0, 120, 'UTF-8');
$templateId = null;
if (isset($data['template_id'])) {
    $templateId = filter_var($data['template_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$templateId) response(false, 'Invalid template ID.', [], 400);
}
$rowCount = count($cleanRows);
$locked = false;
$error = null;
$status = 500;
try {
    // Serialize lookup and write so simultaneous saves cannot insert the same name twice.
    $lock = $conn->query("SELECT GET_LOCK('excel_system_template_save', 10) AS acquired")->fetch_assoc();
    $locked = (int)$lock['acquired'] === 1;
    if (!$locked) throw new RuntimeException('Another save is in progress. Please try again.');
    $conn->begin_transaction();
    $existing = null;
    if ($templateId !== null) {
        $stmt = $conn->prepare('SELECT id FROM templates WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $templateId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$existing) {
            $status = 404;
            throw new RuntimeException('This template was deleted. Generate a new spreadsheet to save a new template.');
        }
    }
    $stmt = $conn->prepare('SELECT id FROM templates WHERE name = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $stmt->bind_param('s', $fileName);
    $stmt->execute();
    $sameName = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing && $sameName && (int)$sameName['id'] !== (int)$existing['id']) {
        // Existing legacy duplicates may share a name; saving the loaded record is still safe.
        $stmt = $conn->prepare('SELECT name FROM templates WHERE id = ?');
        $stmt->bind_param('i', $templateId);
        $stmt->execute();
        $oldName = $stmt->get_result()->fetch_assoc()['name'];
        $stmt->close();
        if ($oldName !== $fileName) {
            $status = 409;
            throw new RuntimeException('Another template already uses this file name. Choose a different name or load that template.');
        }
    }
    $id = $existing ? (int)$existing['id'] : ($sameName ? (int)$sameName['id'] : null);
    $updated = $id !== null;
    if ($updated) {
        $stmt = $conn->prepare('UPDATE templates SET name = ?, column_count = ?, row_count = ?, headers_json = ?, data_json = ? WHERE id = ?');
        $stmt->bind_param('siissi', $fileName, $columnCount, $rowCount, $headersJson, $rowsJson, $id);
    } else {
        $stmt = $conn->prepare('INSERT INTO templates (name, column_count, row_count, headers_json, data_json) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('siiss', $fileName, $columnCount, $rowCount, $headersJson, $rowsJson);
    }
    $stmt->execute();
    if (!$updated) $id = (int)$stmt->insert_id;
    $stmt->close();
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Template save failed: ' . $e->getMessage());
    $error = $status === 500 ? 'Unable to save template. Please try again.' : $e->getMessage();
} finally {
    if ($locked) $conn->query("SELECT RELEASE_LOCK('excel_system_template_save')");
}
if ($error !== null) response(false, $error, [], $status);
response(true, $updated ? 'Template changes saved.' : 'Template saved successfully.', [
    'id' => $id, 'name' => $fileName, 'columns' => $columnCount, 'rows' => $rowCount, 'updated' => $updated
]);
