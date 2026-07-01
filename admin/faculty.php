<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
    exit();
}

require_once("../config/db.php");

try {

    $search = isset($_GET['search']) ? trim($_GET['search']) : "";

    if ($search != "") {

        $stmt = $conn->prepare("
            SELECT *
            FROM faculty
            WHERE
                faculty_id LIKE ?
                OR name LIKE ?
                OR email LIKE ?
                OR subject LIKE ?
            ORDER BY name ASC
        ");

        $keyword = "%$search%";

        $stmt->execute([
            $keyword,
            $keyword,
            $keyword,
            $keyword
        ]);

    } else {

        $stmt = $conn->prepare("
            SELECT *
            FROM faculty
            ORDER BY name ASC
        ");

        $stmt->execute();

    }

    $faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($faculty),
        "faculty" => $faculty
    ]);

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);

}