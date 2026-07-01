<?php

header("Content-Type: application/json");

require_once "../config/db.php";
require_once "attendance_helper.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !is_array($data)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON data"
    ]);
    exit();
}

function cleanText($value, $fallback = "") {
    if ($value === null) {
        return $fallback;
    }

    $text = trim(strval($value));

    return $text === "" ? $fallback : $text;
}

function isTruthy($value) {
    if ($value === null) {
        return false;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return intval($value) === 1;
    }

    $text = strtolower(trim(strval($value)));

    return in_array($text, ["1", "true", "yes", "manual", "manual_override"], true);
}

function getStudentRollNo($student) {
    return cleanText(
        $student["roll_no"] ??
        $student["rollNo"] ??
        $student["roll"] ??
        $student["id"] ??
        ""
    );
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

function normalizeAttendanceStatus($student, $fallbackStatus) {
    $attendanceType = strtolower(cleanText(
        $student["attendance_type"] ??
        $student["attendanceType"] ??
        ""
    ));

    $faceStatus = strtolower(cleanText(
        $student["faceStatus"] ??
        $student["face_status"] ??
        ""
    ));

    if (
        $attendanceType === "manual_present" ||
        $attendanceType === "manual present"
    ) {
        return "Present";
    }

    if (
        $attendanceType === "manual_absent" ||
        $attendanceType === "manual absent"
    ) {
        return "Absent";
    }

    if (
        $attendanceType === "present" ||
        $fallbackStatus === "Present"
    ) {
        return "Present";
    }

    if (
        $attendanceType === "not_registered" ||
        $attendanceType === "not registered" ||
        str_contains($faceStatus, "not registered") ||
        str_contains($faceStatus, "cannot be recognized")
    ) {
        return "Not Registered";
    }

    return "Absent";
}

function detectManualOverride($student) {
    $attendanceType = strtolower(cleanText(
        $student["attendance_type"] ??
        $student["attendanceType"] ??
        ""
    ));

    $faceStatus = strtolower(cleanText(
        $student["faceStatus"] ??
        $student["face_status"] ??
        ""
    ));

    $verificationNote = strtolower(cleanText(
        $student["verification_note"] ??
        $student["verificationNote"] ??
        ""
    ));

    if (isTruthy($student["manual_override"] ?? null)) {
        return 1;
    }

    if (str_contains($attendanceType, "manual")) {
        return 1;
    }

    if (str_contains($faceStatus, "manual")) {
        return 1;
    }

    if (str_contains($verificationNote, "manual")) {
        return 1;
    }

    return 0;
}

function getVerificationNote($student, $manualOverride) {
    $note = cleanText(
        $student["verification_note"] ??
        $student["verificationNote"] ??
        ""
    );

    if ($note !== "") {
        return $note;
    }

    $faceStatus = cleanText(
        $student["faceStatus"] ??
        $student["face_status"] ??
        ""
    );

    if ($manualOverride === 1) {
        if ($faceStatus !== "") {
            return $faceStatus;
        }

        return "Faculty manually verified this attendance because AI confidence was low or face recognition was unclear.";
    }

    return "";
}

function normalizeStudent($student, $fallbackStatus) {
    $rollNo = getStudentRollNo($student);

    if ($rollNo === "") {
        return null;
    }

    $attendanceStatus = normalizeAttendanceStatus($student, $fallbackStatus);
    $manualOverride = detectManualOverride($student);
    $verificationNote = getVerificationNote($student, $manualOverride);

    return [
        "roll_no" => $rollNo,
        "student_name" => cleanText(
            $student["name"] ??
            $student["student_name"] ??
            "Unknown Student"
        ),
        "department" => cleanText($student["department"] ?? "AI & DS"),
        "attendance_status" => $attendanceStatus,
        "manual_override" => $manualOverride,
        "verification_note" => $verificationNote
    ];
}

$report_id = cleanText($data["report_id"] ?? ("REPORT_" . time()));
$class_name = cleanText($data["class_name"] ?? "AI & DS - A");

$subject = cleanText($data["subject"] ?? "Machine Learning");
$session_label = cleanText($data["session_label"] ?? "Morning Session");
$session_time = cleanText($data["session_time"] ?? "");
$attendance_date = cleanText($data["attendance_date"] ?? date("Y-m-d"));

$saved_by = cleanText($data["saved_by"] ?? "Faculty");
$unknown_faces = intval($data["unknown_faces"] ?? 0);
$confidence = cleanText($data["confidence"] ?? "96%");
$image_status = cleanText($data["image_status"] ?? "Clear");
$status = cleanText($data["status"] ?? "Completed");

$present_students_raw = $data["present_students"] ?? [];
$absent_students_raw = $data["absent_students"] ?? [];

if (!is_array($present_students_raw)) {
    $present_students_raw = [];
}

if (!is_array($absent_students_raw)) {
    $absent_students_raw = [];
}

$present_students = [];
$absent_students = [];
$not_registered_students = [];

foreach ($present_students_raw as $student) {
    if (!is_array($student)) {
        continue;
    }

    $normalizedStudent = normalizeStudent($student, "Present");

    if ($normalizedStudent === null) {
        continue;
    }

    $normalizedStudent["attendance_status"] = "Present";
    $present_students[] = $normalizedStudent;
}

foreach ($absent_students_raw as $student) {
    if (!is_array($student)) {
        continue;
    }

    $normalizedStudent = normalizeStudent($student, "Absent");

    if ($normalizedStudent === null) {
        continue;
    }

    if ($normalizedStudent["attendance_status"] === "Not Registered") {
        $not_registered_students[] = $normalizedStudent;
    } else {
        $normalizedStudent["attendance_status"] = "Absent";
        $absent_students[] = $normalizedStudent;
    }
}

$present_count = count($present_students);
$absent_count = count($absent_students);
$not_registered_count = count($not_registered_students);
$total_known_students = $present_count + $absent_count + $not_registered_count;

$all_students_for_manual_count = array_merge(
    $present_students,
    $absent_students,
    $not_registered_students
);

$manual_override_count = 0;

foreach ($all_students_for_manual_count as $student) {
    if (intval($student["manual_override"] ?? 0) === 1) {
        $manual_override_count++;
    }
}

if ($total_known_students === 0 && $unknown_faces === 0) {
    echo json_encode([
        "success" => false,
        "message" => "No attendance data found to save"
    ]);
    exit();
}

try {
    ensureManualOverrideColumns($conn);

    $conn->beginTransaction();

    $checkReportQuery = "SELECT id FROM attendance_reports WHERE report_id = :report_id";
    $checkReportStmt = $conn->prepare($checkReportQuery);
    $checkReportStmt->bindValue(":report_id", $report_id);
    $checkReportStmt->execute();

    if ($checkReportStmt->rowCount() > 0) {
        $conn->rollBack();

        echo json_encode([
            "success" => false,
            "message" => "Attendance report already exists"
        ]);
        exit();
    }

    $checkDuplicateSessionQuery = "SELECT id FROM attendance_reports
                                   WHERE class_name = :class_name
                                   AND subject = :subject
                                   AND session_label = :session_label
                                   AND session_time = :session_time
                                   AND attendance_date = :attendance_date
                                   LIMIT 1";

    $checkDuplicateSessionStmt = $conn->prepare($checkDuplicateSessionQuery);
    $checkDuplicateSessionStmt->bindValue(":class_name", $class_name);
    $checkDuplicateSessionStmt->bindValue(":subject", $subject);
    $checkDuplicateSessionStmt->bindValue(":session_label", $session_label);
    $checkDuplicateSessionStmt->bindValue(":session_time", $session_time);
    $checkDuplicateSessionStmt->bindValue(":attendance_date", $attendance_date);
    $checkDuplicateSessionStmt->execute();

    if ($checkDuplicateSessionStmt->rowCount() > 0) {
        $conn->rollBack();

        echo json_encode([
            "success" => false,
            "message" => "Attendance for this class, subject, session, timing, and date is already saved"
        ]);
        exit();
    }

    $insertReportQuery = "INSERT INTO attendance_reports (
                            report_id,
                            class_name,
                            subject,
                            session_label,
                            session_time,
                            attendance_date,
                            saved_by,
                            present_count,
                            absent_count,
                            unknown_faces,
                            manual_override_count,
                            confidence,
                            image_status,
                            status
                          ) VALUES (
                            :report_id,
                            :class_name,
                            :subject,
                            :session_label,
                            :session_time,
                            :attendance_date,
                            :saved_by,
                            :present_count,
                            :absent_count,
                            :unknown_faces,
                            :manual_override_count,
                            :confidence,
                            :image_status,
                            :status
                          )";

    $reportStmt = $conn->prepare($insertReportQuery);

    $reportStmt->bindValue(":report_id", $report_id);
    $reportStmt->bindValue(":class_name", $class_name);
    $reportStmt->bindValue(":subject", $subject);
    $reportStmt->bindValue(":session_label", $session_label);
    $reportStmt->bindValue(":session_time", $session_time);
    $reportStmt->bindValue(":attendance_date", $attendance_date);
    $reportStmt->bindValue(":saved_by", $saved_by);
    $reportStmt->bindValue(":present_count", $present_count, PDO::PARAM_INT);
    $reportStmt->bindValue(":absent_count", $absent_count, PDO::PARAM_INT);
    $reportStmt->bindValue(":unknown_faces", $unknown_faces, PDO::PARAM_INT);
    $reportStmt->bindValue(":manual_override_count", $manual_override_count, PDO::PARAM_INT);
    $reportStmt->bindValue(":confidence", $confidence);
    $reportStmt->bindValue(":image_status", $image_status);
    $reportStmt->bindValue(":status", $status);

    $reportStmt->execute();

    $insertStudentQuery = "INSERT INTO attendance_report_students (
                            report_id,
                            roll_no,
                            student_name,
                            department,
                            attendance_status,
                            manual_override,
                            verification_note
                          ) VALUES (
                            :report_id,
                            :roll_no,
                            :student_name,
                            :department,
                            :attendance_status,
                            :manual_override,
                            :verification_note
                          )";

    $studentStmt = $conn->prepare($insertStudentQuery);

    $allReportStudents = array_merge(
        $present_students,
        $absent_students,
        $not_registered_students
    );

    foreach ($allReportStudents as $student) {
        $studentStmt->bindValue(":report_id", $report_id);
        $studentStmt->bindValue(":roll_no", $student["roll_no"]);
        $studentStmt->bindValue(":student_name", $student["student_name"]);
        $studentStmt->bindValue(":department", $student["department"]);
        $studentStmt->bindValue(":attendance_status", $student["attendance_status"]);
        $studentStmt->bindValue(":manual_override", intval($student["manual_override"] ?? 0), PDO::PARAM_INT);
        $studentStmt->bindValue(":verification_note", $student["verification_note"] ?? "");
        $studentStmt->execute();
    }

    recalculateAllStudentAttendance($conn);

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Attendance saved and student attendance recalculated",
        "report_id" => $report_id,
        "class_name" => $class_name,
        "subject" => $subject,
        "session_label" => $session_label,
        "session_time" => $session_time,
        "attendance_date" => $attendance_date,
        "saved_by" => $saved_by,
        "present_count" => $present_count,
        "absent_count" => $absent_count,
        "not_registered_count" => $not_registered_count,
        "unknown_faces" => $unknown_faces,
        "manual_override_count" => $manual_override_count,
        "total_known_students" => $total_known_students
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        "success" => false,
        "message" => "Failed to save attendance",
        "error" => $e->getMessage()
    ]);
}

?>