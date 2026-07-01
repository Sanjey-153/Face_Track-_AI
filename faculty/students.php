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

    $search = isset($_GET['search']) ? trim($_GET['search']) : "";

    if (!empty($search)) {

        $stmt = $conn->prepare("
            SELECT *
            FROM students
            WHERE
                roll_no LIKE ?
                OR name LIKE ?
                OR department LIKE ?
            ORDER BY name ASC
        ");

        $keyword = "%{$search}%";

        $stmt->execute([
            $keyword,
            $keyword,
            $keyword
        ]);

    } else {

        $stmt = $conn->prepare("
            SELECT *
            FROM students
            ORDER BY name ASC
        ");

        $stmt->execute();
    }

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($students),
        "students" => $students
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch students.",
        "error" => $e->getMessage()
    ]);
}
?>