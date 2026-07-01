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

    // Read JSON body
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid request body"
        ]);
        exit();
    }

    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if ($username == "" || $password == "") {
        echo json_encode([
            "success" => false,
            "message" => "Username and password are required."
        ]);
        exit();
    }

    /*
        This query supports login using:
        - admin_id
        - username
        - email
    */

    $query = $conn->prepare("
        SELECT *
        FROM admin
        WHERE admin_id = ?
           OR username = ?
           OR email = ?
        LIMIT 1
    ");

    $query->execute([
        $username,
        $username,
        $username
    ]);

    $admin = $query->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {

        echo json_encode([
            "success" => false,
            "message" => "Admin not found."
        ]);

        exit();
    }

    /*
        Supports both:
        Plain password
        Password Hash
    */

    $passwordMatched = false;

    if ($password == $admin['password']) {
        $passwordMatched = true;
    }

    if (password_verify($password, $admin['password'])) {
        $passwordMatched = true;
    }

    if (!$passwordMatched) {

        echo json_encode([
            "success" => false,
            "message" => "Incorrect password."
        ]);

        exit();
    }

    unset($admin['password']);

    echo json_encode([
        "success" => true,
        "message" => "Login Successful",
        "user" => $admin
    ]);

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => "Database Error",
        "error" => $e->getMessage()
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}