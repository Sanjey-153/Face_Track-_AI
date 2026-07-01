<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once("../config/db.php");

try {

    if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {

        echo json_encode([
            "success" => false,
            "message" => "Student ID is required."
        ]);
        exit();
    }

    $studentId = intval($_GET['student_id']);

    $stmt = $conn->prepare("
        SELECT
            id,
            roll_no,
            name,
            department,
            email,
            created_at,
            updated_at
        FROM students
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$studentId]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {

        echo json_encode([
            "success" => false,
            "message" => "Student not found."
        ]);
        exit();
    }

    echo json_encode([
        "success" => true,
        "student" => $student
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database Error",
        "error" => $e->getMessage()
    ]);

}
?>