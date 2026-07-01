<?php

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$roll_no = trim($data["roll_no"] ?? "");
$name = trim($data["name"] ?? "");
$department = trim($data["department"] ?? "");
$email = trim($data["email"] ?? "");
$attendance = $data["attendance"] ?? 0;
$status = trim($data["status"] ?? "Active");

if ($roll_no === "" || $name === "" || $department === "") {
    echo json_encode([
        "success" => false,
        "message" => "Student ID, name, and department are required"
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

    $password = $roll_no;

    $query = "UPDATE students SET
                name = :name,
                department = :department,
                email = :email,
                attendance = :attendance,
                password = :password,
                status = :status
              WHERE roll_no = :roll_no";

    $stmt = $conn->prepare($query);

    $stmt->bindParam(":roll_no", $roll_no);
    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":department", $department);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":attendance", $attendance);
    $stmt->bindParam(":password", $password);
    $stmt->bindParam(":status", $status);

    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Student updated successfully",
        "student" => [
            "roll_no" => $roll_no,
            "name" => $name,
            "department" => $department,
            "email" => $email,
            "attendance" => $attendance,
            "password" => $password,
            "status" => $status
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to update student",
        "error" => $e->getMessage()
    ]);
}