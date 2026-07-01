<?php

function getAttendanceStatus($attendance, $totalClasses = 0) {
    if ($totalClasses <= 0) {
        return "Not Calculated";
    }

    if ($attendance >= 85) {
        return "Excellent";
    }

    if ($attendance >= 75) {
        return "Safe";
    }

    if ($attendance >= 60) {
        return "Warning";
    }

    return "Critical";
}

function normalizeAttendanceStatusForCalculation($status) {
    $status = strtolower(trim(strval($status)));

    if ($status === "present") {
        return "present";
    }

    if ($status === "absent") {
        return "absent";
    }

    if (
        $status === "not registered" ||
        $status === "not_registered" ||
        $status === "face not registered" ||
        $status === "unregistered"
    ) {
        return "not_registered";
    }

    return "ignored";
}

function recalculateAllStudentAttendance($conn) {
    $studentsQuery = "SELECT roll_no FROM students";
    $studentsStmt = $conn->prepare($studentsQuery);
    $studentsStmt->execute();

    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [];

    foreach ($students as $student) {
        $rollNo = trim(strval($student["roll_no"] ?? ""));

        if ($rollNo === "") {
            continue;
        }

        $stats[$rollNo] = [
            "present" => 0,
            "absent" => 0,
            "not_registered" => 0,
            "total" => 0,
            "attendance" => 0
        ];
    }

    $recordsQuery = "SELECT roll_no, attendance_status FROM attendance_report_students";
    $recordsStmt = $conn->prepare($recordsQuery);
    $recordsStmt->execute();

    $records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($records as $record) {
        $rollNo = trim(strval($record["roll_no"] ?? ""));
        $status = normalizeAttendanceStatusForCalculation(
            $record["attendance_status"] ?? ""
        );

        if ($rollNo === "" || !isset($stats[$rollNo])) {
            continue;
        }

        if ($status === "present") {
            $stats[$rollNo]["present"]++;
            continue;
        }

        if ($status === "absent") {
            $stats[$rollNo]["absent"]++;
            continue;
        }

        if ($status === "not_registered") {
            $stats[$rollNo]["not_registered"]++;
            continue;
        }
    }

    foreach ($stats as $rollNo => $studentStats) {
        $present = intval($studentStats["present"]);
        $absent = intval($studentStats["absent"]);

        /*
            IMPORTANT:
            Not Registered students are NOT counted in total_classes.
            Reason:
            They were not recognized because face samples are missing.
            So they should not reduce attendance percentage.
        */
        $total = $present + $absent;

        $attendance = 0.0;

        if ($total > 0) {
            $attendance = ($present / $total) * 100;
        }

        $attendanceStatus = getAttendanceStatus($attendance, $total);
        $lowAttendance = ($attendance < 75 && $total > 0) ? 1 : 0;

        $updateQuery = "UPDATE students SET
                            present_classes = :present_classes,
                            absent_classes = :absent_classes,
                            total_classes = :total_classes,
                            attendance = :attendance,
                            attendance_status = :attendance_status,
                            low_attendance = :low_attendance
                        WHERE roll_no = :roll_no";

        $updateStmt = $conn->prepare($updateQuery);

        $updateStmt->bindValue(":present_classes", $present, PDO::PARAM_INT);
        $updateStmt->bindValue(":absent_classes", $absent, PDO::PARAM_INT);
        $updateStmt->bindValue(":total_classes", $total, PDO::PARAM_INT);
        $updateStmt->bindValue(":attendance", round($attendance, 2));
        $updateStmt->bindValue(":attendance_status", $attendanceStatus);
        $updateStmt->bindValue(":low_attendance", $lowAttendance, PDO::PARAM_INT);
        $updateStmt->bindValue(":roll_no", $rollNo);

        $updateStmt->execute();
    }

    return true;
}
?>