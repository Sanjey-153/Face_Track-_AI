<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once("../config/db.php");

try {

    // =========================
    // Weekly Attendance
    // =========================
    $weeklyStmt = $conn->query("
        SELECT
            DAYNAME(attendance_date) AS day,
            COUNT(*) AS attendance
        FROM attendance_students
        WHERE status = 'Present'
        AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(attendance_date)
        ORDER BY attendance_date ASC
    ");

    $weekly = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

    // =========================
    // Monthly Attendance
    // =========================
    $monthlyStmt = $conn->query("
        SELECT
            DATE_FORMAT(attendance_date, '%Y-%m') AS month,
            COUNT(*) AS attendance
        FROM attendance_students
        WHERE status = 'Present'
        GROUP BY month
        ORDER BY month ASC
    ");

    $monthly = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

    // =========================
    // Department-wise Attendance
    // =========================
    $departmentStmt = $conn->query("
        SELECT
            s.department,
            COUNT(*) AS attendance
        FROM attendance_students a
        INNER JOIN students s
            ON a.student_id = s.id
        WHERE a.status = 'Present'
        GROUP BY s.department
        ORDER BY attendance DESC
    ");

    $department = $departmentStmt->fetchAll(PDO::FETCH_ASSOC);

    // =========================
    // Faculty-wise Reports
    // =========================
    $facultyStmt = $conn->query("
        SELECT
            saved_by AS faculty,
            COUNT(*) AS reports
        FROM attendance_reports
        GROUP BY saved_by
        ORDER BY reports DESC
    ");

    $faculty = $facultyStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "weekly" => $weekly,
        "monthly" => $monthly,
        "department" => $department,
        "faculty" => $faculty
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to load dashboard charts.",
        "error" => $e->getMessage()
    ]);
}
?>