<?php

require_once "UserRepository.php";

try {
    $repository = new UserRepository($conn);
    $email = "kaniziyusi." . time() . "@example.com";
    $userId = $repository->insert(3, "Kaniziyusi", $email, "0781234577");

    echo "User inserted successfully. New ID: {$userId}" . PHP_EOL;
} catch (PDOException $e) {
    echo "Failed to insert user: " . $e->getMessage() . PHP_EOL;
}

?>