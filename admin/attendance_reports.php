<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once("../config/db.php");

try {

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 20;

    $offset = ($page - 1) * $limit;

    $class = $_GET['class_name'] ?? '';
    $subject = $_GET['subject'] ?? '';
    $faculty = $_GET['saved_by'] ?? '';
    $date = $_GET['attendance_date'] ?? '';

    $where = [];
    $params = [];

    if (!empty($class)) {
        $where[] = "class_name LIKE :class_name";
        $params[':class_name'] = "%$class%";
    }

    if (!empty($subject)) {
        $where[] = "subject LIKE :subject";
        $params[':subject'] = "%$subject%";
    }

    if (!empty($faculty)) {
        $where[] = "saved_by LIKE :saved_by";
        $params[':saved_by'] = "%$faculty%";
    }

    if (!empty($date)) {
        $where[] = "attendance_date = :attendance_date";
        $params[':attendance_date'] = $date;
    }

    $whereSql = "";

    if (!empty($where)) {
        $whereSql = " WHERE " . implode(" AND ", $where);
    }

    // Total Count
    $countSql = "
        SELECT COUNT(*) AS total
        FROM attendance_reports
        $whereSql
    ";

    $countStmt = $conn->prepare($countSql);

    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }

    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Reports Query
    $sql = "
        SELECT *
        FROM attendance_reports
        $whereSql
        ORDER BY attendance_date DESC, report_id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "total" => (int)$total,
        "page" => $page,
        "limit" => $limit,
        "total_pages" => ceil($total / $limit),
        "reports" => $reports
    ]);
}
catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch attendance reports.",
        "error" => $e->getMessage()
    ]);
}
?>