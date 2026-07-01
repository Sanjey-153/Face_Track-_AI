<?php

require_once "../config/db.php";
require_once "attendance_helper.php";

try {
    recalculateAllStudentAttendance($conn);

    echo json_encode([
        "success" => true,
        "message" => "All student attendance recalculated successfully"
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to recalculate attendance",
        "error" => $e->getMessage()
    ]);
}