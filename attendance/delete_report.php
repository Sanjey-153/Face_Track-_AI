<?php

require_once "../config/db.php";
require_once "attendance_helper.php";

$data = json_decode(file_get_contents("php://input"), true);

$report_id = trim($data["report_id"] ?? "");

if ($report_id === "") {
    echo json_encode([
        "success" => false,
        "message" => "Report ID is required"
    ]);
    exit();
}

try {
    $conn->beginTransaction();

    $checkQuery = "SELECT id FROM attendance_reports WHERE report_id = :report_id";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(":report_id", $report_id);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        $conn->rollBack();

        echo json_encode([
            "success" => false,
            "message" => "Report not found"
        ]);
        exit();
    }

    $deleteStudentsQuery = "DELETE FROM attendance_report_students WHERE report_id = :report_id";
    $deleteStudentsStmt = $conn->prepare($deleteStudentsQuery);
    $deleteStudentsStmt->bindParam(":report_id", $report_id);
    $deleteStudentsStmt->execute();

    $deleteReportQuery = "DELETE FROM attendance_reports WHERE report_id = :report_id";
    $deleteReportStmt = $conn->prepare($deleteReportQuery);
    $deleteReportStmt->bindParam(":report_id", $report_id);
    $deleteReportStmt->execute();

    recalculateAllStudentAttendance($conn);

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Report deleted and attendance recalculated",
        "report_id" => $report_id
    ]);
} catch (Exception $e) {
    $conn->rollBack();

    echo json_encode([
        "success" => false,
        "message" => "Failed to delete report",
        "error" => $e->getMessage()
    ]);
}