<?php

header("Content-Type: application/json");

require_once "../config/db.php";

$FACE_MATCH_THRESHOLD = 0.88;

function sendResponse($success, $message, $extra = []) {
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit();
}

function normalizeEmbedding($embedding) {
    $sum = 0;

    foreach ($embedding as $value) {
        $sum += $value * $value;
    }

    $norm = sqrt($sum);

    if ($norm == 0) {
        return $embedding;
    }

    $normalized = [];

    foreach ($embedding as $value) {
        $normalized[] = $value / $norm;
    }

    return $normalized;
}

function parseEmbedding($embeddingText) {
    if ($embeddingText === null || trim($embeddingText) === "") {
        return null;
    }

    $decoded = json_decode($embeddingText, true);

    if (!is_array($decoded)) {
        return null;
    }

    $embedding = [];

    foreach ($decoded as $value) {
        if (!is_numeric($value)) {
            return null;
        }

        $embedding[] = floatval($value);
    }

    if (count($embedding) < 64) {
        return null;
    }

    return normalizeEmbedding($embedding);
}

function cosineSimilarity($a, $b) {
    if (!is_array($a) || !is_array($b)) {
        return 0;
    }

    if (count($a) !== count($b)) {
        return 0;
    }

    $dot = 0;
    $normA = 0;
    $normB = 0;

    for ($i = 0; $i < count($a); $i++) {
        $dot += $a[$i] * $b[$i];
        $normA += $a[$i] * $a[$i];
        $normB += $b[$i] * $b[$i];
    }

    if ($normA == 0 || $normB == 0) {
        return 0;
    }

    return $dot / (sqrt($normA) * sqrt($normB));
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendResponse(false, "Only POST method is allowed");
}

$roll_no = trim($_POST["roll_no"] ?? "");
$face_embedding_text = trim($_POST["face_embedding"] ?? "");

if ($roll_no === "") {
    sendResponse(false, "Student ID / Roll No is required");
}

$newEmbedding = parseEmbedding($face_embedding_text);

if ($newEmbedding === null) {
    sendResponse(
        false,
        "Wrong ❌ Valid face embedding is required. Use MobileFaceNet registration screen."
    );
}

if (!isset($_FILES["face_image"])) {
    sendResponse(false, "Face image is required");
}

$file = $_FILES["face_image"];

if ($file["error"] !== UPLOAD_ERR_OK) {
    sendResponse(false, "Image upload failed", [
        "error_code" => $file["error"]
    ]);
}

$allowedTypes = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp"
];

$fileType = mime_content_type($file["tmp_name"]);

if (!array_key_exists($fileType, $allowedTypes)) {
    sendResponse(false, "Only JPG, PNG, and WEBP images are allowed");
}

try {
    /*
        1. Check student exists.
    */
    $checkStudentQuery = "SELECT 
                            id, 
                            roll_no, 
                            name,
                            face_registered,
                            face_status,
                            face_image,
                            face_hash,
                            face_embedding
                          FROM students
                          WHERE roll_no = :roll_no
                          LIMIT 1";

    $checkStudentStmt = $conn->prepare($checkStudentQuery);
    $checkStudentStmt->bindValue(":roll_no", $roll_no);
    $checkStudentStmt->execute();

    if ($checkStudentStmt->rowCount() === 0) {
        sendResponse(false, "Student not found");
    }

    $student = $checkStudentStmt->fetch(PDO::FETCH_ASSOC);
    $studentName = trim($student["name"] ?? "");

    $newFaceHash = hash_file("sha256", $file["tmp_name"]);

    /*
        2. Check exact duplicate image for the same student.
        Same student can have many samples, but not the exact same image again.
    */
    $sameStudentSampleQuery = "SELECT id
                               FROM student_face_embeddings
                               WHERE roll_no = :roll_no
                               AND face_hash = :face_hash
                               LIMIT 1";

    $sameStudentSampleStmt = $conn->prepare($sameStudentSampleQuery);
    $sameStudentSampleStmt->bindValue(":roll_no", $roll_no);
    $sameStudentSampleStmt->bindValue(":face_hash", $newFaceHash);
    $sameStudentSampleStmt->execute();

    if ($sameStudentSampleStmt->rowCount() > 0) {
        sendResponse(false, "This exact face sample is already added for this student.");
    }

    /*
        3. Check whether the uploaded face belongs to another student.
        This prevents one student's face being added under another roll number.
    */
    $registeredQuery = "SELECT 
                            s.roll_no,
                            s.name,
                            s.face_hash,
                            s.face_embedding,
                            e.face_hash AS sample_face_hash,
                            e.face_embedding AS sample_face_embedding
                        FROM students s
                        LEFT JOIN student_face_embeddings e
                        ON s.roll_no = e.roll_no
                        WHERE s.roll_no != :roll_no
                        AND (
                            s.face_registered = 1
                            OR s.face_embedding IS NOT NULL
                            OR e.face_embedding IS NOT NULL
                        )";

    $registeredStmt = $conn->prepare($registeredQuery);
    $registeredStmt->bindValue(":roll_no", $roll_no);
    $registeredStmt->execute();

    $registeredStudents = $registeredStmt->fetchAll(PDO::FETCH_ASSOC);

    $bestEmbeddingMatch = null;
    $bestSimilarity = 0;

    foreach ($registeredStudents as $registeredStudent) {
        $existingRollNo = trim($registeredStudent["roll_no"] ?? "");
        $existingName = trim($registeredStudent["name"] ?? "");

        $mainFaceHash = trim($registeredStudent["face_hash"] ?? "");
        $sampleFaceHash = trim($registeredStudent["sample_face_hash"] ?? "");

        if ($mainFaceHash !== "" && $mainFaceHash === $newFaceHash) {
            sendResponse(false, "Wrong ❌ This exact image is already used for another student.", [
                "match_type" => "exact_main_image_hash",
                "registered_student" => [
                    "roll_no" => $existingRollNo,
                    "name" => $existingName
                ]
            ]);
        }

        if ($sampleFaceHash !== "" && $sampleFaceHash === $newFaceHash) {
            sendResponse(false, "Wrong ❌ This exact face sample is already used for another student.", [
                "match_type" => "exact_sample_image_hash",
                "registered_student" => [
                    "roll_no" => $existingRollNo,
                    "name" => $existingName
                ]
            ]);
        }

        $mainEmbedding = parseEmbedding($registeredStudent["face_embedding"] ?? "");
        $sampleEmbedding = parseEmbedding($registeredStudent["sample_face_embedding"] ?? "");

        if ($mainEmbedding !== null) {
            $similarity = cosineSimilarity($newEmbedding, $mainEmbedding);

            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestEmbeddingMatch = [
                    "roll_no" => $existingRollNo,
                    "name" => $existingName,
                    "source" => "main_face"
                ];
            }
        }

        if ($sampleEmbedding !== null) {
            $similarity = cosineSimilarity($newEmbedding, $sampleEmbedding);

            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestEmbeddingMatch = [
                    "roll_no" => $existingRollNo,
                    "name" => $existingName,
                    "source" => "face_sample"
                ];
            }
        }
    }

    if ($bestEmbeddingMatch !== null && $bestSimilarity >= $FACE_MATCH_THRESHOLD) {
        sendResponse(false, "Wrong ❌ This face is already registered for another student.", [
            "match_type" => "face_embedding",
            "similarity" => round($bestSimilarity, 4),
            "threshold" => $FACE_MATCH_THRESHOLD,
            "registered_student" => [
                "roll_no" => $bestEmbeddingMatch["roll_no"],
                "name" => $bestEmbeddingMatch["name"],
                "source" => $bestEmbeddingMatch["source"]
            ]
        ]);
    }

    /*
        4. Auto-create sample label.
    */
    $sampleCountQuery = "SELECT COUNT(*) AS sample_count
                         FROM student_face_embeddings
                         WHERE roll_no = :roll_no";

    $sampleCountStmt = $conn->prepare($sampleCountQuery);
    $sampleCountStmt->bindValue(":roll_no", $roll_no);
    $sampleCountStmt->execute();

    $sampleCountRow = $sampleCountStmt->fetch(PDO::FETCH_ASSOC);
    $existingSampleCount = intval($sampleCountRow["sample_count"] ?? 0);

    $nextSampleNumber = $existingSampleCount + 1;
    $sampleLabel = "Face Sample " . $nextSampleNumber;

    /*
        5. Save sample image file.
        This file is for recognition samples only.
        It does NOT replace the student profile image.
    */
    $uploadDir = "../uploads/faces/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = $allowedTypes[$fileType];
    $safeRollNo = preg_replace("/[^a-zA-Z0-9_-]/", "_", $roll_no);
    $fileName = $safeRollNo . "_sample_" . $nextSampleNumber . "_" . time() . "_" . rand(1000, 9999) . "." . $extension;

    $targetPath = $uploadDir . $fileName;
    $databasePath = "uploads/faces/" . $fileName;

    if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
        sendResponse(false, "Failed to save image file");
    }

    $embeddingJson = json_encode($newEmbedding);

    /*
        6. Insert only into student_face_embeddings.
        This is the important fix:
        - profile image is not changed
        - students.face_image is not changed
        - students.face_embedding is not changed
    */
    $insertSampleQuery = "INSERT INTO student_face_embeddings (
                            roll_no,
                            student_name,
                            face_image,
                            face_hash,
                            face_embedding,
                            face_embedding_model,
                            sample_label
                          ) VALUES (
                            :roll_no,
                            :student_name,
                            :face_image,
                            :face_hash,
                            :face_embedding,
                            'MobileFaceNet',
                            :sample_label
                          )";

    $insertSampleStmt = $conn->prepare($insertSampleQuery);
    $insertSampleStmt->bindValue(":roll_no", $roll_no);
    $insertSampleStmt->bindValue(":student_name", $studentName);
    $insertSampleStmt->bindValue(":face_image", $databasePath);
    $insertSampleStmt->bindValue(":face_hash", $newFaceHash);
    $insertSampleStmt->bindValue(":face_embedding", $embeddingJson);
    $insertSampleStmt->bindValue(":sample_label", $sampleLabel);
    $insertSampleStmt->execute();

    /*
        7. Only update registration status.
        We do NOT update face_image, face_hash, or face_embedding in students table.
    */
    $updateStatusQuery = "UPDATE students SET
                            face_registered = 1,
                            face_status = 'Registered',
                            face_embedding_model = 'MobileFaceNet',
                            face_registered_at = COALESCE(face_registered_at, NOW())
                          WHERE roll_no = :roll_no";

    $updateStatusStmt = $conn->prepare($updateStatusQuery);
    $updateStatusStmt->bindValue(":roll_no", $roll_no);
    $updateStatusStmt->execute();

    sendResponse(true, "Additional face sample added successfully", [
        "duplicate_check" => [
            "best_similarity" => round($bestSimilarity, 4),
            "face_match_threshold" => $FACE_MATCH_THRESHOLD,
            "is_unique_face" => true
        ],
        "student" => [
            "roll_no" => $roll_no,
            "name" => $studentName,
            "face_registered" => true,
            "face_status" => "Registered",
            "profile_image_updated" => false,
            "sample_image" => $databasePath,
            "sample_label" => $sampleLabel,
            "sample_count" => $nextSampleNumber
        ]
    ]);
} catch (Exception $e) {
    sendResponse(false, "Face sample registration failed", [
        "error" => $e->getMessage()
    ]);
}

?>