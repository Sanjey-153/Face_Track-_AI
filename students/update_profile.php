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

    if (!$data) {
        throw new Exception("Invalid request.");
    }

    $studentId  = $data['student_id'] ?? '';
    $name       = trim($data['name'] ?? '');
    $email      = trim($data['email'] ?? '');
    $department = trim($data['department'] ?? '');

    if (empty($studentId) || empty($name) || empty($email)) {
        throw new Exception("Required fields are missing.");
    }

    // Check duplicate email
    $stmt = $conn->prepare("
        SELECT id
        FROM students
        WHERE email = ?
        AND id <> ?
    ");

    $stmt->execute([$email, $studentId]);

    if ($stmt->rowCount() > 0) {
        throw new Exception("Email already exists.");
    }

    $stmt = $conn->prepare("
        UPDATE students
        SET
            name = ?,
            email = ?,
            department = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $name,
        $email,
        $department,
        $studentId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Profile updated successfully."
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>