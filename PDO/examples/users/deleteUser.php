<?php
require_once __DIR__ . '/cli_only.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/helper/getId.php';

try {

    $user_id = getPositiveIntFromGetId('id');

    $sql = "DELETE FROM users
            WHERE user_id = :user_id";


    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ":user_id" => $user_id
    ]);

    echo "User deleted successfully.";

} catch (PDOException $e) {

    echo "Failed to delete user: " . $e->getMessage();

}

?>
