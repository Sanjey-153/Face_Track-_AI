<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once("../config/db.php");

try {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['report_id'])) {
        throw new Exception("report_id is required.");
    }

    $reportId = (int)$data['report_id'];

    $conn->beginTransaction();

    // Delete attendance students
    $stmt1 = $conn->prepare("
        DELETE FROM attendance_students
        WHERE report_id = ?
    ");

    $stmt1->execute([$reportId]);

    // Delete report
    $stmt2 = $conn->prepare("
        DELETE FROM attendance_reports
        WHERE report_id = ?
    ");

    $stmt2->execute([$reportId]);

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Attendance report deleted successfully."
    ]);
}
catch (Exception $e) {

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