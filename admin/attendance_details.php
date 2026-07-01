<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once("../config/db.php");

try {

    if (!isset($_GET['report_id'])) {
        throw new Exception("report_id is required.");
    }

    $reportId = (int)$_GET['report_id'];

    // Get report information
    $stmt = $conn->prepare("
        SELECT *
        FROM attendance_reports
        WHERE report_id = ?
    ");

    $stmt->execute([$reportId]);

    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        throw new Exception("Attendance report not found.");
    }

    // Get students for this report
    $studentStmt = $conn->prepare("
        SELECT *
        FROM attendance_students
        WHERE report_id = ?
        ORDER BY name ASC
    ");

    $studentStmt->execute([$reportId]);

    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

    $presentStudents = [];
    $absentStudents = [];

    foreach ($students as $student) {
        if (
            isset($student['status']) &&
            strtolower($student['status']) === 'present'
        ) {
            $presentStudents[] = $student;
        } else {
            $absentStudents[] = $student;
        }
    }

    echo json_encode([
        "success" => true,
        "report" => $report,
        "statistics" => [
            "total_students" => count($students),
            "present_count" => count($presentStudents),
            "absent_count" => count($absentStudents)
        ],
        "present_students" => $presentStudents,
        "absent_students" => $absentStudents
    ]);
}
catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>