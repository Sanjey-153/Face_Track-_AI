<?php

header("Content-Type: application/json");

require_once "../config/db.php";

function cleanText($value, $fallback = "") {
    if ($value === null) {
        return $fallback;
    }

    $text = trim(strval($value));

    return $text === "" ? $fallback : $text;
}

function columnExists($conn, $tableName, $columnName) {
    $query = "SELECT COUNT(*) AS count_value
              FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(":table_name", $tableName);
    $stmt->bindValue(":column_name", $columnName);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return intval($row["count_value"] ?? 0) > 0;
}

function addColumnIfMissing($conn, $tableName, $columnName, $definition) {
    $allowedTables = ["attendance_reports", "attendance_report_students"];
    $allowedColumns = ["manual_override_count", "manual_override", "verification_note"];

    if (!in_array($tableName, $allowedTables, true)) {
        return;
    }

    if (!in_array($columnName, $allowedColumns, true)) {
        return;
    }

    if (!columnExists($conn, $tableName, $columnName)) {
        $query = "ALTER TABLE $tableName ADD COLUMN $columnName $definition";
        $conn->exec($query);
    }
}

function ensureManualOverrideColumns($conn) {
    addColumnIfMissing(
        $conn,
        "attendance_report_students",
        "manual_override",
        "TINYINT(1) NOT NULL DEFAULT 0 AFTER attendance_status"
    );

    addColumnIfMissing(
        $conn,
        "attendance_report_students",
        "verification_note",
        "TEXT NULL AFTER manual_override"
    );

    addColumnIfMissing(
        $conn,
        "attendance_reports",
        "manual_override_count",
        "INT NOT NULL DEFAULT 0 AFTER unknown_faces"
    );
}

try {
    ensureManualOverrideColumns($conn);

    $reportsQuery = "SELECT * FROM attendance_reports 
                     ORDER BY attendance_date DESC, created_at DESC";

    $reportsStmt = $conn->prepare($reportsQuery);
    $reportsStmt->execute();

    $reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reports as &$report) {
        $report_id = $report["report_id"];

        if (!isset($report["subject"]) || trim($report["subject"]) === "") {
            $report["subject"] = "Machine Learning";
        }

        if (!isset($report["session_label"]) || trim($report["session_label"]) === "") {
            $report["session_label"] = "Morning Session";
        }

        if (!isset($report["session_time"])) {
            $report["session_time"] = "";
        }

        if (!isset($report["attendance_date"]) || trim($report["attendance_date"]) === "") {
            if (isset($report["created_at"]) && trim($report["created_at"]) !== "") {
                $report["attendance_date"] = date("Y-m-d", strtotime($report["created_at"]));
            } else {
                $report["attendance_date"] = date("Y-m-d");
            }
        }

        if (!isset($report["manual_override_count"])) {
            $report["manual_override_count"] = 0;
        }

        $studentsQuery = "SELECT 
                            roll_no,
                            student_name,
                            department,
                            attendance_status,
                            manual_override,
                            verification_note
                          FROM attendance_report_students
                          WHERE report_id = :report_id
                          ORDER BY student_name ASC";

        $studentsStmt = $conn->prepare($studentsQuery);
        $studentsStmt->bindParam(":report_id", $report_id);
        $studentsStmt->execute();

        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

        $presentStudents = [];
        $absentStudents = [];
        $notRegisteredStudents = [];
        $manualOverrideStudents = [];

        $manualOverrideCount = 0;

        foreach ($students as &$student) {
            $status = strtolower(cleanText($student["attendance_status"] ?? ""));
            $manualOverride = intval($student["manual_override"] ?? 0);

            $student["manual_override"] = $manualOverride;
            $student["verification_note"] = cleanText($student["verification_note"] ?? "");

            if ($manualOverride === 1) {
                $manualOverrideCount++;
                $manualOverrideStudents[] = $student;
            }

            if ($status === "present") {
                $presentStudents[] = $student;
            } else if ($status === "absent") {
                $absentStudents[] = $student;
            } else if ($status === "not registered" || $status === "not_registered") {
                $notRegisteredStudents[] = $student;
            }
        }

        $report["manual_override_count"] = $manualOverrideCount;

        $report["present_students"] = $presentStudents;
        $report["absent_students"] = $absentStudents;
        $report["not_registered_students"] = $notRegisteredStudents;
        $report["manual_override_students"] = $manualOverrideStudents;

        $report["total_known_students"] =
            count($presentStudents) +
            count($absentStudents) +
            count($notRegisteredStudents);
    }

    echo json_encode([
        "success" => true,
        "count" => count($reports),
        "reports" => $reports
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch reports",
        "error" => $e->getMessage()
    ]);
}

?>