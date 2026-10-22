<?php

require_once __DIR__ . '/cli_only.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/helper/getId.php';
require_once __DIR__ . "/UserRepository.php";


try {
    $user_id = getPositiveIntFromGetId('id');
    $repository = new UserRepository($conn);
    $updatedRows = $repository->update($user_id, "Jean  mugisha", "baptiste@gmail.com", "0784961277");

    echo "User updated successfully. Rows changed: {$updatedRows}" . PHP_EOL;

} catch (PDOException $e) {
    echo "Failed to update user: " . $e->getMessage() . PHP_EOL;
}
