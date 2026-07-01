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

    $studentId = (int)$_GET['student_id'];

    // ==========================
    // Overall Attendance
    // ==========================

    $stmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN attendance_status='Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN attendance_status='Absent' THEN 1 ELSE 0 END) AS absent_count
        FROM attendance_report_students
        WHERE student_id=?
    ");

    $stmt->execute([$studentId]);

    $overall = $stmt->fetch(PDO::FETCH_ASSOC);

    $present = (int)($overall['present_count'] ?? 0);
    $absent  = (int)($overall['absent_count'] ?? 0);

    $total = $present + $absent;

    $percentage = 0;

    if ($total > 0) {
        $percentage = round(($present / $total) * 100, 2);
    }

    // ==========================
    // Subject-wise Attendance
    // ==========================

    $stmt = $conn->prepare("
        SELECT

            ar.subject,

            SUM(CASE WHEN ars.attendance_status='Present' THEN 1 ELSE 0 END) AS present,

            SUM(CASE WHEN ars.attendance_status='Absent' THEN 1 ELSE 0 END) AS absent

        FROM attendance_report_students ars

        INNER JOIN attendance_reports ar
            ON ars.report_id = ar.report_id

        WHERE ars.student_id = ?

        GROUP BY ar.subject

        ORDER BY ar.subject ASC
    ");

    $stmt->execute([$studentId]);

    $subjects = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $subjectPresent = (int)$row['present'];
        $subjectAbsent  = (int)$row['absent'];

        $subjectTotal = $subjectPresent + $subjectAbsent;

        $subjectPercentage = 0;

        if ($subjectTotal > 0) {
            $subjectPercentage = round(
                ($subjectPresent / $subjectTotal) * 100,
                2
            );
        }

        $subjects[] = [

            "subject" => $row['subject'],

            "present" => $subjectPresent,

            "absent" => $subjectAbsent,

            "total_classes" => $subjectTotal,

            "attendance_percentage" => $subjectPercentage

        ];
    }

    echo json_encode([

        "success" => true,

        "overall" => [

            "present" => $present,

            "absent" => $absent,

            "total_classes" => $total,

            "attendance_percentage" => $percentage

        ],

        "subjects" => $subjects

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