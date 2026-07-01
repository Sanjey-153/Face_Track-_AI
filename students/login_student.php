<?php

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$roll_no = trim($data["roll_no"] ?? "");
$password = trim($data["password"] ?? "");

if ($roll_no === "" || $password === "") {
    echo json_encode([
        "success" => false,
        "message" => "Student ID and password are required"
    ]);
    exit();
}

try {
    $query = "SELECT * FROM students 
              WHERE roll_no = :roll_no 
              AND password = :password 
              LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(":roll_no", $roll_no);
    $stmt->bindParam(":password", $password);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid student ID or password"
        ]);
        exit();
    }

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "message" => "Student login successful",
        "student" => $student
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Login failed",
        "error" => $e->getMessage()
    ]);
}