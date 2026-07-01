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

    // ===========================
    // Faculty Information
    // ===========================

    $stmt = $conn->prepare("
        SELECT
            id,
            faculty_id,
            name,
            subject,
            email,
            designation,
            status
        FROM faculty
        WHERE faculty_id = ?
        LIMIT 1
    ");

    $stmt->execute([$facultyId]);

    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$faculty) {
        echo json_encode([
            "success" => false,
            "message" => "Faculty not found."
        ]);
        exit();
    }

    // ===========================
    // Total Students
    // ===========================

    $totalStudents = $conn->query("
        SELECT COUNT(*) FROM students
    ")->fetchColumn();

    // ===========================
    // Total Attendance Reports
    // ===========================

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM attendance_reports
        WHERE saved_by = ?
    ");

    $stmt->execute([$facultyId]);

    $totalReports = $stmt->fetchColumn();

    // ===========================
    // Today's Reports
    // ===========================

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM attendance_reports
        WHERE saved_by = ?
        AND attendance_date = CURDATE()
    ");

    $stmt->execute([$facultyId]);

    $todayReports = $stmt->fetchColumn();

    // ===========================
    // Recent Attendance Reports
    // ===========================

    $stmt = $conn->prepare("
        SELECT
            report_id,
            class_name,
            subject,
            attendance_date,
            session_time
        FROM attendance_reports
        WHERE saved_by = ?
        ORDER BY attendance_date DESC,
                 report_id DESC
        LIMIT 5
    ");

    $stmt->execute([$facultyId]);

    $recentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===========================
    // Response
    // ===========================

    echo json_encode([

        "success" => true,

        "faculty" => $faculty,

        "statistics" => [

            "total_students" => (int)$totalStudents,

            "total_reports" => (int)$totalReports,

            "today_reports" => (int)$todayReports

        ],

        "recent_reports" => $recentReports

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