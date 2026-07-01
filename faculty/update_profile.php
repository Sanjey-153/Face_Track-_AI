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

    if (!$data) {
        throw new Exception("Invalid request.");
    }

    $facultyId   = trim($data['faculty_id'] ?? '');
    $name        = trim($data['name'] ?? '');
    $email       = trim($data['email'] ?? '');
    $subject     = trim($data['subject'] ?? '');
    $designation = trim($data['designation'] ?? '');

    if (
        empty($facultyId) ||
        empty($name) ||
        empty($email)
    ) {
        throw new Exception("Required fields are missing.");
    }

    // Check duplicate email
    $stmt = $conn->prepare("
        SELECT id
        FROM faculty
        WHERE email = ?
        AND faculty_id <> ?
    ");

    $stmt->execute([$email, $facultyId]);

    if ($stmt->rowCount() > 0) {
        throw new Exception("Email already exists.");
    }

    $stmt = $conn->prepare("
        UPDATE faculty
        SET
            name = ?,
            email = ?,
            subject = ?,
            designation = ?,
            updated_at = NOW()
        WHERE faculty_id = ?
    ");

    $stmt->execute([
        $name,
        $email,
        $subject,
        $designation,
        $facultyId
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