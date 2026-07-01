<?php

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$faculty_id = trim($data["faculty_id"] ?? "");

if ($faculty_id === "") {
    echo json_encode([
        "success" => false,
        "message" => "Faculty ID is required"
    ]);
    exit();
}

try {
    $checkQuery = "SELECT id FROM faculty WHERE faculty_id = :faculty_id";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(":faculty_id", $faculty_id);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Faculty not found"
        ]);
        exit();
    }

    $query = "DELETE FROM faculty WHERE faculty_id = :faculty_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":faculty_id", $faculty_id);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Faculty deleted successfully",
        "faculty_id" => $faculty_id
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to delete faculty",
        "error" => $e->getMessage()
    ]);
}