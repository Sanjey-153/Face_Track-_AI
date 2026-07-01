<?php

require_once "../config/db.php";

try {
    $query = "UPDATE faculty SET password = faculty_id";
    $stmt = $conn->prepare($query);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "All faculty passwords updated to Faculty ID",
        "updated_rows" => $stmt->rowCount()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to reset faculty passwords",
        "error" => $e->getMessage()
    ]);
}