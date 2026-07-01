<?php

require_once "../config/db.php";

try {
    $query = "SELECT 
                id,
                faculty_id,
                name,
                subject,
                email,
                designation,
                password,
                status,
                created_at,
                updated_at
              FROM faculty
              ORDER BY name ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute();

    $faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($faculty),
        "faculty" => $faculty
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch faculty",
        "error" => $e->getMessage()
    ]);
}