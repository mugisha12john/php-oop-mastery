<?php

require_once "UserRepository.php";

try {
    $repository = new UserRepository($conn);

    echo "FETCH_ASSOC" . PHP_EOL;
    print_r($repository->getAllAssoc());

    echo "FETCH_ARRAY (numeric array)" . PHP_EOL;
    print_r($repository->getAllArray());

    echo "FETCH_OBJECT" . PHP_EOL;
    print_r($repository->getAllObject());

} catch (PDOException $e) {
    echo "Failed to fetch users: " . $e->getMessage() . PHP_EOL;
}