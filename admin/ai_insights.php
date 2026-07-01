<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once("../config/db.php");

try {

    // ============================
    // Overall Attendance
    // ============================

    $stmt = $conn->query("
        SELECT
            SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS absent_count
        FROM attendance_students
    ");

    $overall = $stmt->fetch(PDO::FETCH_ASSOC);

    $present = (int)$overall['present_count'];
    $absent = (int)$overall['absent_count'];

    $total = $present + $absent;

    $overallPercentage = $total > 0
        ? round(($present / $total) * 100, 2)
        : 0;

    // ============================
    // Department Performance
    // ============================

    $deptStmt = $conn->query("
        SELECT
            s.department,

            ROUND(
                (
                    SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END)
                    /
                    COUNT(a.id)
                ) * 100,
                2
            ) AS attendance_percentage

        FROM attendance_students a

        INNER JOIN students s
            ON a.student_id = s.id

        GROUP BY s.department
        ORDER BY attendance_percentage DESC
    ");

    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

    $topDepartment = null;
    $lowestDepartment = null;

    if (count($departments) > 0) {
        $topDepartment = $departments[0];
        $lowestDepartment = $departments[count($departments)-1];
    }

    // ============================
    // Low Attendance Students
    // ============================

    $lowStmt = $conn->query("
        SELECT COUNT(*) AS total
        FROM (

            SELECT
                s.id,

                ROUND(
                    (
                        SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END)
                        /
                        COUNT(a.id)
                    ) * 100,
                    2
                ) AS percentage

            FROM students s

            LEFT JOIN attendance_students a
                ON s.id=a.student_id

            GROUP BY s.id

            HAVING percentage < 75
                OR percentage IS NULL

        ) t
    ");

    $lowAttendanceStudents =
        (int)$lowStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ============================
    // Registered Faces
    // ============================

    $stmt = $conn->query("
        SELECT COUNT(*) total
        FROM students
        WHERE face_encoding IS NOT NULL
        AND face_encoding <> ''
    ");

    $registeredFaces =
        (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ============================
    // Attendance Reports
    // ============================

    $stmt = $conn->query("
        SELECT COUNT(*) total
        FROM attendance_reports
    ");

    $totalReports =
        (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ============================
    // AI Suggestions
    // ============================

    $suggestions = [];

    if ($overallPercentage < 70) {
        $suggestions[] =
            "Overall attendance is below 70%. Conduct awareness sessions.";
    }

    if ($lowAttendanceStudents > 0) {
        $suggestions[] =
            "$lowAttendanceStudents students require attendance counseling.";
    }

    if ($registeredFaces < 10) {
        $suggestions[] =
            "Several students have not registered their face data.";
    }

    if ($topDepartment != null) {
        $suggestions[] =
            "Best performing department is " .
            $topDepartment['department'] .
            " (" .
            $topDepartment['attendance_percentage'] .
            "%).";
    }

    if ($lowestDepartment != null) {
        $suggestions[] =
            "Department needing improvement: " .
            $lowestDepartment['department'] .
            " (" .
            $lowestDepartment['attendance_percentage'] .
            "%).";
    }

    // ============================
    // Response
    // ============================

    echo json_encode([

        "success" => true,

        "overall_attendance" => $overallPercentage,

        "present" => $present,

        "absent" => $absent,

        "registered_faces" => $registeredFaces,

        "low_attendance_students" => $lowAttendanceStudents,

        "total_reports" => $totalReports,

        "top_department" => $topDepartment,

        "lowest_department" => $lowestDepartment,

        "suggestions" => $suggestions,

        "generated_at" => date("Y-m-d H:i:s")

    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([

        "success" => false,

        "message" => "Failed to generate AI insights.",

        "error" => $e->getMessage()

    ]);

}
?>0