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

    $search = trim($_GET['search'] ?? '');

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;

    $params = [$studentId];

    $where = "WHERE ars.student_id = ?";

    if (!empty($search)) {

        $where .= " AND (
            ar.subject LIKE ?
            OR ar.class_name LIKE ?
            OR ar.attendance_date LIKE ?
            OR ars.attendance_status LIKE ?
        )";

        $keyword = "%{$search}%";

        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
    }

    // Total Records
    $countSql = "
        SELECT COUNT(*)
        FROM attendance_report_students ars
        INNER JOIN attendance_reports ar
            ON ars.report_id = ar.report_id
        $where
    ";

    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);

    $totalRecords = $countStmt->fetchColumn();

    // Attendance History
    $sql = "
        SELECT
            ar.report_id,
            ar.class_name,
            ar.subject,
            ar.attendance_date,
            ar.session_time,
            ars.attendance_status
        FROM attendance_report_students ars
        INNER JOIN attendance_reports ar
            ON ars.report_id = ar.report_id
        $where
        ORDER BY ar.attendance_date DESC,
                 ar.report_id DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "page" => $page,
        "limit" => $limit,
        "total_records" => (int)$totalRecords,
        "total_pages" => ceil($totalRecords / $limit),
        "attendance_history" => $history
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