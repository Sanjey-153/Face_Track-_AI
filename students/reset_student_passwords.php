<?php

require_once "../config/db.php";

try {
    $query = "UPDATE students SET password = roll_no";
    $stmt = $conn->prepare($query);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "All student passwords updated to Student ID",
        "updated_rows" => $stmt->rowCount()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to reset student passwords",
        "error" => $e->getMessage()
    ]);
}