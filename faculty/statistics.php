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

    // Verify Faculty
    $stmt = $conn->prepare("
        SELECT id, faculty_id, name
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

    // Total Students
    $totalStudents = $conn->query("
        SELECT COUNT(*) FROM students
    ")->fetchColumn();

    // Total Attendance Reports by Faculty
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM attendance_reports
        WHERE saved_by = ?
    ");

    $stmt->execute([$facultyId]);
    $totalReports = $stmt->fetchColumn();

    // Today's Reports
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM attendance_reports
        WHERE saved_by = ?
        AND attendance_date = CURDATE()
    ");

    $stmt->execute([$facultyId]);
    $todayReports = $stmt->fetchColumn();

    // Present Students
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM attendance_report_students ars
        INNER JOIN attendance_reports ar
            ON ars.report_id = ar.report_id
        WHERE ar.saved_by = ?
        AND ars.status = 'Present'
    ");

    $stmt->execute([$facultyId]);
    $presentStudents = $stmt->fetchColumn();

    // Absent Students
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM attendance_report_students ars
        INNER JOIN attendance_reports ar
            ON ars.report_id = ar.report_id
        WHERE ar.saved_by = ?
        AND ars.status = 'Absent'
    ");

    $stmt->execute([$facultyId]);
    $absentStudents = $stmt->fetchColumn();

    // Attendance Percentage
    $attendancePercentage = 0;

    if (($presentStudents + $absentStudents) > 0) {
        $attendancePercentage = round(
            ($presentStudents / ($presentStudents + $absentStudents)) * 100,
            2
        );
    }

    echo json_encode([
        "success" => true,

        "statistics" => [

            "total_students" => (int)$totalStudents,

            "total_reports" => (int)$totalReports,

            "today_reports" => (int)$todayReports,

            "present_students" => (int)$presentStudents,

            "absent_students" => (int)$absentStudents,

            "attendance_percentage" => $attendancePercentage
        ]
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