<?php

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$roll_no = trim($data["roll_no"] ?? "");

if ($roll_no === "") {
    echo json_encode([
        "success" => false,
        "message" => "Student ID is required"
    ]);
    exit();
}

try {
    $checkQuery = "SELECT id FROM students WHERE roll_no = :roll_no";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(":roll_no", $roll_no);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Student not found"
        ]);
        exit();
    }

    $query = "DELETE FROM students WHERE roll_no = :roll_no";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":roll_no", $roll_no);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Student deleted successfully",
        "roll_no" => $roll_no
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to delete student",
        "error" => $e->getMessage()
    ]);
}