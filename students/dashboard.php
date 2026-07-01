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

    if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
        echo json_encode([
            "success" => false,
            "message" => "Student ID is required."
        ]);
        exit();
    }

    $studentId = intval($_GET['student_id']);

    // Student Information
    $stmt = $conn->prepare("
        SELECT
            id,
            roll_no,
            name,
            department,
            email
        FROM students
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$studentId]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode([
            "success" => false,
            "message" => "Student not found."
        ]);
        exit();
    }

    // Present Count
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM attendance_report_students
        WHERE student_id = ?
        AND attendance_status='Present'
    ");

    $stmt->execute([$studentId]);
    $present = $stmt->fetchColumn();

    // Absent Count
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM attendance_report_students
        WHERE student_id = ?
        AND attendance_status='Absent'
    ");

    $stmt->execute([$studentId]);
    $absent = $stmt->fetchColumn();

    $total = $present + $absent;

    $percentage = 0;

    if ($total > 0) {
        $percentage = round(($present / $total) * 100, 2);
    }

    // Recent Attendance
    $stmt = $conn->prepare("
        SELECT
            ar.report_id,
            ar.subject,
            ar.class_name,
            ar.attendance_date,
            ars.attendance_status
        FROM attendance_report_students ars

        INNER JOIN attendance_reports ar
            ON ars.report_id = ar.report_id

        WHERE ars.student_id = ?

        ORDER BY ar.attendance_date DESC

        LIMIT 5
    ");

    $stmt->execute([$studentId]);

    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([

        "success" => true,

        "student" => $student,

        "statistics" => [

            "present" => (int)$present,

            "absent" => (int)$absent,

            "total_classes" => (int)$total,

            "attendance_percentage" => $percentage

        ],

        "recent_attendance" => $recentAttendance

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