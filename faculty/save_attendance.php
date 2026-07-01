<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once("../config/db.php");

try {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        throw new Exception("Invalid JSON data.");
    }

    $facultyId = trim($data['faculty_id'] ?? '');
    $className = trim($data['class_name'] ?? '');
    $subject = trim($data['subject'] ?? '');
    $attendanceDate = trim($data['attendance_date'] ?? date('Y-m-d'));
    $sessionTime = trim($data['session_time'] ?? '');
    $students = $data['students'] ?? [];

    if (
        empty($facultyId) ||
        empty($className) ||
        empty($subject) ||
        empty($students)
    ) {
        throw new Exception("Required fields are missing.");
    }

    $conn->beginTransaction();

    // ===========================
    // Create Attendance Report
    // ===========================

    $stmt = $conn->prepare("
        INSERT INTO attendance_reports
        (
            class_name,
            subject,
            attendance_date,
            session_time,
            saved_by
        )
        VALUES
        (?,?,?,?,?)
    ");

    $stmt->execute([
        $className,
        $subject,
        $attendanceDate,
        $sessionTime,
        $facultyId
    ]);

    $reportId = $conn->lastInsertId();

    // ===========================
    // Save Students Attendance
    // ===========================

    $studentStmt = $conn->prepare("
        INSERT INTO attendance_report_students
        (
            report_id,
            student_id,
            attendance_status
        )
        VALUES
        (?,?,?)
    ");

    $present = 0;
    $absent = 0;

    foreach ($students as $student) {

        $studentId = $student['student_id'];
        $status = ucfirst(strtolower($student['status']));

        if ($status == "Present") {
            $present++;
        } else {
            $absent++;
        }

        $studentStmt->execute([
            $reportId,
            $studentId,
            $status
        ]);
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Attendance saved successfully.",
        "report_id" => (int)$reportId,
        "statistics" => [
            "total_students" => count($students),
            "present" => $present,
            "absent" => $absent
        ]
    ]);

} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>