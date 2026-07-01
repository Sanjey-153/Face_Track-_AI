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

    if (!isset($_GET['report_id']) || empty($_GET['report_id'])) {

        echo json_encode([
            "success" => false,
            "message" => "Report ID is required."
        ]);
        exit();
    }

    $reportId = intval($_GET['report_id']);

    // ===========================
    // Report Information
    // ===========================

    $stmt = $conn->prepare("
        SELECT
            report_id,
            class_name,
            subject,
            attendance_date,
            session_time,
            saved_by
        FROM attendance_reports
        WHERE report_id = ?
        LIMIT 1
    ");

    $stmt->execute([$reportId]);

    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {

        echo json_encode([
            "success" => false,
            "message" => "Attendance report not found."
        ]);
        exit();
    }

    // ===========================
    // Student Attendance
    // ===========================

    $stmt = $conn->prepare("
        SELECT
            ars.student_id,
            s.roll_no,
            s.name,
            s.department,
            ars.attendance_status
        FROM attendance_report_students ars
        INNER JOIN students s
            ON ars.student_id = s.id
        WHERE ars.report_id = ?
        ORDER BY s.name ASC
    ");

    $stmt->execute([$reportId]);

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $presentStudents = [];
    $absentStudents = [];

    foreach ($records as $student) {

        if (strtolower($student['attendance_status']) == "present") {
            $presentStudents[] = $student;
        } else {
            $absentStudents[] = $student;
        }
    }

    $totalStudents = count($records);
    $presentCount = count($presentStudents);
    $absentCount = count($absentStudents);

    $attendancePercentage = 0;

    if ($totalStudents > 0) {
        $attendancePercentage = round(
            ($presentCount / $totalStudents) * 100,
            2
        );
    }

    echo json_encode([

        "success" => true,

        "report" => $report,

        "summary" => [

            "total_students" => $totalStudents,

            "present_students" => $presentCount,

            "absent_students" => $absentCount,

            "attendance_percentage" => $attendancePercentage

        ],

        "present_list" => $presentStudents,

        "absent_list" => $absentStudents

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