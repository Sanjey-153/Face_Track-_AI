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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "success" => false,
            "message" => "Invalid request method."
        ]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON request."
        ]);
        exit();
    }

    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if (empty($username) || empty($password)) {

        echo json_encode([
            "success" => false,
            "message" => "Faculty ID/Email and Password are required."
        ]);

        exit();
    }

    $stmt = $conn->prepare("
        SELECT *
        FROM faculty
        WHERE faculty_id = ?
           OR email = ?
        LIMIT 1
    ");

    $stmt->execute([
        $username,
        $username
    ]);

    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$faculty) {

        echo json_encode([
            "success" => false,
            "message" => "Faculty not found."
        ]);

        exit();
    }

    if ($faculty['status'] !== "Active") {

        echo json_encode([
            "success" => false,
            "message" => "Faculty account is inactive."
        ]);

        exit();
    }

    // Plain-text password comparison
    if ($password !== $faculty['password']) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid password."
        ]);

        exit();
    }

    unset($faculty['password']);

    echo json_encode([
        "success" => true,
        "message" => "Login successful.",
        "faculty" => $faculty
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database error.",
        "error" => $e->getMessage()
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}
?>