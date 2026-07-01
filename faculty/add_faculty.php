<?php

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$faculty_id = trim($data["faculty_id"] ?? "");
$name = trim($data["name"] ?? "");
$subject = trim($data["subject"] ?? "");
$email = trim($data["email"] ?? "");
$designation = trim($data["designation"] ?? "Faculty");

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

    if ($checkStmt->rowCount() > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Faculty already exists"
        ]);
        exit();
    }

    $password = $faculty_id;

    $query = "INSERT INTO faculty (
                faculty_id,
                name,
                subject,
                email,
                designation,
                password,
                status
              ) VALUES (
                :faculty_id,
                :name,
                :subject,
                :email,
                :designation,
                :password,
                'Active'
              )";

    $stmt = $conn->prepare($query);

    $stmt->bindParam(":faculty_id", $faculty_id);
    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":subject", $subject);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":designation", $designation);
    $stmt->bindParam(":password", $password);

    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Faculty added successfully",
        "faculty" => [
            "faculty_id" => $faculty_id,
            "name" => $name,
            "subject" => $subject,
            "email" => $email,
            "designation" => $designation,
            "password" => $password
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to add faculty",
        "error" => $e->getMessage()
    ]);
}