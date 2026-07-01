<?php

header("Content-Type: application/json");

require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$roll_no = "";

if (isset($_POST["roll_no"])) {
    $roll_no = trim($_POST["roll_no"]);
} elseif (isset($data["roll_no"])) {
    $roll_no = trim($data["roll_no"]);
} elseif (isset($_GET["roll_no"])) {
    $roll_no = trim($_GET["roll_no"]);
}

if ($roll_no === "") {
    echo json_encode([
        "success" => false,
        "message" => "Student ID / Roll No is required"
    ]);
    exit();
}

try {
    $studentQuery = "SELECT roll_no, name, face_image
                     FROM students
                     WHERE roll_no = :roll_no
                     LIMIT 1";

    $studentStmt = $conn->prepare($studentQuery);
    $studentStmt->bindValue(":roll_no", $roll_no);
    $studentStmt->execute();

    if ($studentStmt->rowCount() === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Student not found"
        ]);
        exit();
    }

    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($student["face_image"])) {
        $oldImagePath = "../" . $student["face_image"];

        if (file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }
    }

    $clearQuery = "UPDATE students SET
                    face_registered = 0,
                    face_status = 'Not Registered',
                    face_image = '',
                    face_hash = '',
                    face_phash = NULL,
                    face_embedding = NULL,
                    face_embedding_model = NULL,
                    face_registered_at = NULL
                  WHERE roll_no = :roll_no";

    $clearStmt = $conn->prepare($clearQuery);
    $clearStmt->bindValue(":roll_no", $roll_no);
    $clearStmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Face registration cleared successfully",
        "student" => [
            "roll_no" => $roll_no,
            "name" => $student["name"]
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to clear face registration",
        "error" => $e->getMessage()
    ]);
}

?>