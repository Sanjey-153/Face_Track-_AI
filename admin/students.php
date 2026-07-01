<?php

require_once "../config/db.php";

header("Content-Type: application/json");

try {

    $search = isset($_GET['search']) ? trim($_GET['search']) : "";

    if ($search != "") {

        $sql = "SELECT *
                FROM students
                WHERE
                    name LIKE :search
                    OR roll_no LIKE :search
                    OR department LIKE :search
                ORDER BY name ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ":search" => "%$search%"
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