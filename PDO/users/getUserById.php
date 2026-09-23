<?php

require_once "UserRepository.php";

$userId = (int) ($argv[1] ?? 1);

try {
    $repository = new UserRepository($conn);

    echo "FETCH_ASSOC" . PHP_EOL;
    print_r($repository->findByIdAssoc($userId));

    echo "FETCH_ARRAY (numeric array)" . PHP_EOL;
    print_r($repository->findByIdArray($userId));

    echo "FETCH_OBJECT" . PHP_EOL;
    print_r($repository->findByIdObject($userId));
} catch (PDOException $e) {
    echo "Failed to fetch user: " . $e->getMessage() . PHP_EOL;
}