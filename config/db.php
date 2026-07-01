<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com";
$port = "4000";
$db_name = "sys";
$username = "4KXXUZPfUzEziz4.root";
$password = "iMDUArHraEbfqqP6";

try {
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4",
        $username,
        $password
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
        "error" => $e->getMessage()
    ]);
    exit();
}
