<?php

require_once "UserRepository.php";

try {
    $repository = new UserRepository($conn);
    $updatedRows = $repository->update(1, "Jean baptiste mugisha", "mugisha004@gmail.com", "0784961277");

    echo "User updated successfully. Rows changed: {$updatedRows}" . PHP_EOL;

} catch (PDOException $e) {
    echo "Failed to update user: " . $e->getMessage() . PHP_EOL;
}