<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "excel_system";

$conn = new mysqli(
    $host,
    $username,
    $password
);

if ($conn->connect_error) {
    die(
        "Database connection failed: " .
        $conn->connect_error
    );
}

$conn->set_charset("utf8mb4");

$sql = "
    CREATE DATABASE IF NOT EXISTS `$database`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci
";

if (!$conn->query($sql)) {
    die(
        "Database creation failed: " .
        $conn->error
    );
}

if (!$conn->select_db($database)) {
    die(
        "Unable to select database: " .
        $conn->error
    );
}

$sql = "
    CREATE TABLE IF NOT EXISTS templates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        column_count INT UNSIGNED NOT NULL DEFAULT 1,
        row_count INT UNSIGNED NOT NULL DEFAULT 0,
        headers_json LONGTEXT NOT NULL,
        data_json LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP
    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_unicode_ci
";

if (!$conn->query($sql)) {
    die(
        "Table creation failed: " .
        $conn->error
    );
}
?>