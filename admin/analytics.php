<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once("../config/db.php");

try {

    // Total Students
    $stmt = $conn->query("
        SELECT COUNT(*) AS total
        FROM students
    ");
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total Faculty
    $stmt = $conn->query("
        SELECT COUNT(*) AS total
        FROM faculty
    ");
    $totalFaculty = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Registered Faces
    $stmt = $conn->query("
        SELECT COUNT(*) AS total
        FROM students
        WHERE face_encoding IS NOT NULL
        AND face_encoding <> ''
    ");
    $registeredFaces = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Today's Attendance
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM attendance_students
        WHERE DATE(attendance_date) = CURDATE()
        AND status = 'Present'
    ");
    $stmt->execute();
    $todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Overall Present
    $stmt = $conn->query("
        SELECT COUNT(*) AS total
        FROM attendance_students
        WHERE status='Present'
    ");
    $overallPresent = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Overall Absent
    $stmt = $conn->query("
        SELECT COUNT(*) AS total
        FROM attendance_students
        WHERE status='Absent'
    ");
    $overallAbsent = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Attendance Percentage
    $totalAttendance = $overallPresent + $overallAbsent;

    $attendancePercentage = 0;

    if ($totalAttendance > 0) {
        $attendancePercentage =
            round(($overallPresent / $totalAttendance) * 100, 2);
    }

    echo json_encode([
        "success" => true,
        "total_students" => (int)$totalStudents,
        "total_faculty" => (int)$totalFaculty,
        "registered_faces" => (int)$registeredFaces,
        "today_attendance" => (int)$todayAttendance,
        "overall_present" => (int)$overallPresent,
        "overall_absent" => (int)$overallAbsent,
        "attendance_percentage" => $attendancePercentage
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to load analytics.",
        "error" => $e->getMessage()
    ]);
}
?>