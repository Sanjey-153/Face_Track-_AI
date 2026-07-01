<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
    exit();
}

require_once("../config/db.php");

try {

    $data = json_decode(file_get_contents("php://input"), true);

    $roll_no = trim($data['roll_no'] ?? '');
    $name = trim($data['name'] ?? '');
    $department = trim($data['department'] ?? '');
    $email = trim($data['email'] ?? '');

    if (
        empty($roll_no) ||
        empty($name) ||
        empty($department) ||
        empty($email)
    ) {
        echo json_encode([
            "success" => false,
            "message" => "All fields are required."
        ]);
        exit();
    }

    // Check duplicate Roll Number
    $stmt = $conn->prepare("SELECT id FROM students WHERE roll_no=?");
    $stmt->execute([$roll_no]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Roll Number already exists."
        ]);
        exit();
    }

    // Check duplicate Email
    $stmt = $conn->prepare("SELECT id FROM students WHERE email=?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Email already exists."
        ]);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO students
        (
            roll_no,
            name,
            department,
            email
        )
        VALUES
        (?,?,?,?)
    ");

    $stmt->execute([
        $roll_no,
        $name,
        $department,
        $email
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Student added successfully."
    ]);

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}