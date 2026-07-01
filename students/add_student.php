<?php

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$roll_no = trim($data["roll_no"] ?? "");
$name = trim($data["name"] ?? "");
$department = trim($data["department"] ?? "");
$email = trim($data["email"] ?? "");
$attendance = $data["attendance"] ?? 0;

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

    if ($checkStmt->rowCount() > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Student already exists"
        ]);
        exit();
    }

    $query = "INSERT INTO students (
                roll_no,
                name,
                department,
                email,
                password,
                attendance,
                present_classes,
                absent_classes,
                total_classes,
                attendance_status,
                low_attendance,
                face_registered,
                face_status,
                status
              ) VALUES (
                :roll_no,
                :name,
                :department,
                :email,
                :password,
                :attendance,
                0,
                0,
                0,
                'Not Calculated',
                0,
                0,
                'Not Registered',
                'Active'
              )";

    $stmt = $conn->prepare($query);

    $password = $roll_no;

    $stmt->bindParam(":roll_no", $roll_no);
    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":department", $department);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password", $password);
    $stmt->bindParam(":attendance", $attendance);

    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Student added successfully",
        "student" => [
            "roll_no" => $roll_no,
            "name" => $name,
            "department" => $department,
            "email" => $email,
            "password" => $password
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to add student",
        "error" => $e->getMessage()
    ]);
}