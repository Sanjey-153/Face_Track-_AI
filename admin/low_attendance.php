<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once("../config/db.php");

try {

    // Attendance threshold (default: 75%)
    $threshold = isset($_GET['threshold'])
        ? (float)$_GET['threshold']
        : 75;

    $sql = "
        SELECT
            s.id,
            s.roll_no,
            s.name,
            s.department,

            SUM(CASE WHEN ats.status='Present' THEN 1 ELSE 0 END) AS present_count,
            COUNT(ats.id) AS total_classes,

            ROUND(
                (
                    SUM(CASE WHEN ats.status='Present' THEN 1 ELSE 0 END)
                    /
                    NULLIF(COUNT(ats.id),0)
                ) * 100,
                2
            ) AS attendance_percentage

        FROM students s

        LEFT JOIN attendance_students ats
            ON s.id = ats.student_id

        GROUP BY s.id

        HAVING attendance_percentage < :threshold
            OR attendance_percentage IS NULL

        ORDER BY attendance_percentage ASC,
                 s.name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(":threshold", $threshold);
    $stmt->execute();

    $students = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $students[] = [

            "student_id" => (int)$row["id"],

            "roll_no" => $row["roll_no"],

            "name" => $row["name"],

            "department" => $row["department"],

            "present_classes" => (int)$row["present_count"],

            "total_classes" => (int)$row["total_classes"],

            "attendance_percentage" => $row["attendance_percentage"] == null
                ? 0
                : (float)$row["attendance_percentage"]

        ];
    }

    echo json_encode([

        "success" => true,

        "threshold" => $threshold,

        "total_students" => count($students),

        "students" => $students

    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([

        "success" => false,

        "message" => "Failed to load low attendance students.",

        "error" => $e->getMessage()

    ]);
}
?>