<?php

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$faculty_id = trim($data["faculty_id"] ?? "");
$name = trim($data["name"] ?? "");
$subject = trim($data["subject"] ?? "");
$email = trim($data["email"] ?? "");
$designation = trim($data["designation"] ?? "Faculty");
$status = trim($data["status"] ?? "Active");

if ($faculty_id === "" || $name === "" || $subject === "") {
    echo json_encode([
        "success" => false,
        "message" => "Faculty ID, name, and subject are required"
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

    $password = $faculty_id;

    $query = "UPDATE faculty SET
                name = :name,
                subject = :subject,
                email = :email,
                designation = :designation,
                password = :password,
                status = :status
              WHERE faculty_id = :faculty_id";

    $stmt = $conn->prepare($query);

    $stmt->bindParam(":faculty_id", $faculty_id);
    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":subject", $subject);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":designation", $designation);
    $stmt->bindParam(":password", $password);
    $stmt->bindParam(":status", $status);

    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Faculty updated successfully",
        "faculty" => [
            "faculty_id" => $faculty_id,
            "name" => $name,
            "subject" => $subject,
            "email" => $email,
            "designation" => $designation,
            "password" => $password,
            "status" => $status
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to update faculty",
        "error" => $e->getMessage()
    ]);
}