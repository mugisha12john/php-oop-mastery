<?php

require_once "UserRepository.php";

try {
    $repository = new UserRepository($conn);
    $email = "kigalicollege." . time() . "@example.com";
    $userId = $repository->insert(2, "Noah", $email, "07861277");
   

    echo "User inserted successfully. New ID: {$userId}" . PHP_EOL;
} catch (PDOException $e) {
    echo "Failed to insert user: " . $e->getMessage() . PHP_EOL;
}

?>