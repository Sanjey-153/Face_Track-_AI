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

    if (!isset($_GET['faculty_id']) || empty($_GET['faculty_id'])) {

        echo json_encode([
            "success" => false,
            "message" => "Faculty ID is required."
        ]);
        exit();
    }

    $facultyId = trim($_GET['faculty_id']);
    $search = trim($_GET['search'] ?? "");

    if ($search != "") {

        $stmt = $conn->prepare("
            SELECT
                report_id,
                class_name,
                subject,
                attendance_date,
                session_time,
                saved_by
            FROM attendance_reports
            WHERE saved_by = ?
            AND (
                class_name LIKE ?
                OR subject LIKE ?
                OR attendance_date LIKE ?
            )
            ORDER BY attendance_date DESC,
                     report_id DESC
        ");

        $keyword = "%{$search}%";

        $stmt->execute([
            $facultyId,
            $keyword,
            $keyword,
            $keyword
        ]);

    } else {

        $stmt = $conn->prepare("
            SELECT
                report_id,
                class_name,
                subject,
                attendance_date,
                session_time,
                saved_by
            FROM attendance_reports
            WHERE saved_by = ?
            ORDER BY attendance_date DESC,
                     report_id DESC
        ");

        $stmt->execute([$facultyId]);

    }

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($reports),
        "attendance_history" => $reports
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database Error",
        "error" => $e->getMessage()
    ]);

}
?>