<?php

$dsn = "mysql:host=localhost;dbname=money_lending_db;charset=utf8mb4";

try {
    $conn = new PDO($dsn, "app_user", "buban1234", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    exit("Connection failed: " . $e->getMessage() . PHP_EOL);
}