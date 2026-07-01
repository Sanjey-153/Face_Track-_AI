<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once("../config/db.php");

try {

    $data = json_decode(file_get_contents("php://input"), true);

    $facultyId = trim($data['faculty_id'] ?? '');
    $oldPassword = trim($data['old_password'] ?? '');
    $newPassword = trim($data['new_password'] ?? '');

    if (
        empty($facultyId) ||
        empty($oldPassword) ||
        empty($newPassword)
    ) {
        throw new Exception("All fields are required.");
    }

    $stmt = $conn->prepare("
        SELECT password
        FROM faculty
        WHERE faculty_id = ?
    ");

    $stmt->execute([$facultyId]);

    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$faculty) {
        throw new Exception("Faculty not found.");
    }

    if ($faculty['password'] !== $oldPassword) {
        throw new Exception("Old password is incorrect.");
    }

    $stmt = $conn->prepare("
        UPDATE faculty
        SET
            password = ?,
            updated_at = NOW()
        WHERE faculty_id = ?
    ");

    $stmt->execute([
        $newPassword,
        $facultyId
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