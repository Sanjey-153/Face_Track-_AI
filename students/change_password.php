<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
}

require_once("../config/db.php");

try {

    $data = json_decode(file_get_contents("php://input"), true);

    $studentId = $data['student_id'] ?? '';
    $oldPassword = trim($data['old_password'] ?? '');
    $newPassword = trim($data['new_password'] ?? '');

    if (
        empty($studentId) ||
        empty($oldPassword) ||
        empty($newPassword)
    ) {
        throw new Exception("All fields are required.");
    }

    $stmt = $conn->prepare("
        SELECT password
        FROM students
        WHERE id = ?
    ");

    $stmt->execute([$studentId]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception("Student not found.");
    }

    if ($student['password'] !== $oldPassword) {
        throw new Exception("Old password is incorrect.");
    }

    $stmt = $conn->prepare("
        UPDATE students
        SET
            password = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $newPassword,
        $studentId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Password changed successfully."
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>