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

    $search = trim($_GET['search'] ?? "");

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;

    $params = [$facultyId];

    $where = " WHERE saved_by = ? ";

    if (!empty($search)) {
        $where .= " AND (
            class_name LIKE ?
            OR subject LIKE ?
            OR attendance_date LIKE ?
        )";

        $keyword = "%{$search}%";

        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
    }

    // Total Reports
    $countSql = "SELECT COUNT(*) FROM attendance_reports $where";

    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);

    $totalReports = $countStmt->fetchColumn();

    // Fetch Reports
    $sql = "
        SELECT
            report_id,
            class_name,
            subject,
            attendance_date,
            session_time,
            saved_by
        FROM attendance_reports
        $where
        ORDER BY attendance_date DESC,
                 report_id DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "page" => $page,
        "limit" => $limit,
        "total_reports" => (int)$totalReports,
        "total_pages" => ceil($totalReports / $limit),
        "reports" => $reports
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