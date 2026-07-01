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

    // -------------------------------
    // Total Students
    // -------------------------------
    $stmt = $conn->query("SELECT COUNT(*) FROM students");
    $totalStudents = (int)$stmt->fetchColumn();

    // -------------------------------
    // Total Faculty
    // -------------------------------
    $stmt = $conn->query("SELECT COUNT(*) FROM faculty");
    $totalFaculty = (int)$stmt->fetchColumn();

    // -------------------------------
    // Total Attendance Reports
    // -------------------------------
    $stmt = $conn->query("SELECT COUNT(*) FROM attendance_reports");
    $totalReports = (int)$stmt->fetchColumn();

    // -------------------------------
    // Registered Faces
    // -------------------------------
    $stmt = $conn->query("SELECT COUNT(*) FROM student_face_embeddings");
    $registeredFaces = (int)$stmt->fetchColumn();

    // -------------------------------
    // Total Present Students
    // -------------------------------
    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM attendance_report_students
        WHERE LOWER(attendance_status)='present'
    ");
    $presentStudents = (int)$stmt->fetchColumn();

    // -------------------------------
    // Total Absent Students
    // -------------------------------
    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM attendance_report_students
        WHERE LOWER(attendance_status)='absent'
    ");
    $absentStudents = (int)$stmt->fetchColumn();

    // -------------------------------
    // Attendance Percentage
    // -------------------------------
    $totalAttendance = $presentStudents + $absentStudents;

    if ($totalAttendance > 0) {
        $attendanceRate = round(
            ($presentStudents / $totalAttendance) * 100,
            2
        );
    } else {
        $attendanceRate = 0;
    }

    // -------------------------------
    // Recent Attendance Reports
    // -------------------------------
    $stmt = $conn->prepare("
        SELECT
            report_id,
            class_name,
            subject,
            attendance_date,
            session_time,
            saved_by
        FROM attendance_reports
        ORDER BY attendance_date DESC, report_id DESC
        LIMIT 5
    ");

    $stmt->execute();

    $recentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------
    // Dashboard Response
    // -------------------------------
    echo json_encode([
        "success" => true,
        "statistics" => [

            "total_students" => $totalStudents,

            "total_faculty" => $totalFaculty,

            "attendance_reports" => $totalReports,

            "registered_faces" => $registeredFaces,

            "present_students" => $presentStudents,

            "absent_students" => $absentStudents,

            "attendance_percentage" => $attendanceRate
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