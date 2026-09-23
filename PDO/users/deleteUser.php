<?php

require_once "../connection.php";

try {

    // ID of the user we want to delete
    $user_id = 2;

    // DELETE query
    $sql = "DELETE FROM users
            WHERE user_id = :user_id";

    // Prepare the query
    $stmt = $conn->prepare($sql);

    // Execute the query
    $stmt->execute([
        ":user_id" => $user_id
    ]);

    echo "User deleted successfully.";

} catch (PDOException $e) {

    echo "Failed to delete user: " . $e->getMessage();

}

?>