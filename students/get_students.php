<?php

header("Content-Type: application/json");

require_once "../config/db.php";

try {
    $query = "SELECT 
                id,
                roll_no,
                name,
                department,
                email,
                password,
                attendance,
                present_classes,
                absent_classes,
                total_classes,
                attendance_status,
                low_attendance,
                face_registered,
                face_status,
                face_image,
                face_hash,
                face_embedding,
                face_embedding_model,
                face_registered_at,
                status,
                created_at,
                updated_at
              FROM students
              ORDER BY name ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute();

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $samplesQuery = "SELECT 
                        id,
                        roll_no,
                        student_name,
                        face_image,
                        face_hash,
                        face_embedding,
                        face_embedding_model,
                        sample_label,
                        created_at
                     FROM student_face_embeddings
                     ORDER BY roll_no ASC, created_at ASC";

    $samplesStmt = $conn->prepare($samplesQuery);
    $samplesStmt->execute();

    $samples = $samplesStmt->fetchAll(PDO::FETCH_ASSOC);

    $samplesByRollNo = [];

    foreach ($samples as $sample) {
        $sampleRollNo = trim($sample["roll_no"] ?? "");

        if ($sampleRollNo === "") {
            continue;
        }

        if (!isset($samplesByRollNo[$sampleRollNo])) {
            $samplesByRollNo[$sampleRollNo] = [];
        }

        $samplesByRollNo[$sampleRollNo][] = [
            "id" => intval($sample["id"] ?? 0),
            "roll_no" => $sampleRollNo,
            "student_name" => $sample["student_name"] ?? "",
            "face_image" => $sample["face_image"] ?? "",
            "face_hash" => $sample["face_hash"] ?? "",
            "face_embedding" => $sample["face_embedding"] ?? null,
            "face_embedding_model" => $sample["face_embedding_model"] ?? "MobileFaceNet",
            "sample_label" => $sample["sample_label"] ?? "Face Sample",
            "created_at" => $sample["created_at"] ?? null,
            "source" => "sample_table"
        ];
    }

    foreach ($students as &$student) {
        $rollNo = trim($student["roll_no"] ?? "");

        $student["face_registered"] = intval($student["face_registered"] ?? 0);
        $student["low_attendance"] = intval($student["low_attendance"] ?? 0);

        if (!isset($student["face_embedding"])) {
            $student["face_embedding"] = null;
        }

        if (!isset($student["face_embedding_model"]) || trim($student["face_embedding_model"] ?? "") === "") {
            $student["face_embedding_model"] = "MobileFaceNet";
        }

        if (!isset($student["face_registered_at"])) {
            $student["face_registered_at"] = null;
        }

        $faceSamples = [];

        if (
            isset($student["face_embedding"]) &&
            trim($student["face_embedding"] ?? "") !== ""
        ) {
            $faceSamples[] = [
                "id" => 0,
                "roll_no" => $rollNo,
                "student_name" => $student["name"] ?? "",
                "face_image" => $student["face_image"] ?? "",
                "face_hash" => $student["face_hash"] ?? "",
                "face_embedding" => $student["face_embedding"],
                "face_embedding_model" => $student["face_embedding_model"] ?? "MobileFaceNet",
                "sample_label" => "Main Registered Face",
                "created_at" => $student["face_registered_at"] ?? null,
                "source" => "students_table"
            ];
        }

        if ($rollNo !== "" && isset($samplesByRollNo[$rollNo])) {
            foreach ($samplesByRollNo[$rollNo] as $sample) {
                $faceSamples[] = $sample;
            }
        }

        $student["face_samples"] = $faceSamples;
        $student["face_sample_count"] = count($faceSamples);
    }

    echo json_encode([
        "success" => true,
        "count" => count($students),
        "students" => $students
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch students",
        "error" => $e->getMessage()
    ]);
}

?>