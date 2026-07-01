<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD']=="OPTIONS") {
    exit();
}

require_once("../config/db.php");

try {

    $data=json_decode(file_get_contents("php://input"),true);

    $studentId=$data['id'];

    $conn->beginTransaction();

    // Delete student's face embeddings
    $stmt=$conn->prepare("
        DELETE FROM student_face_embeddings
        WHERE student_id=?
    ");

    $stmt->execute([$studentId]);

    // Delete student
    $stmt=$conn->prepare("
        DELETE FROM students
        WHERE id=?
    ");

    $stmt->execute([$studentId]);

    $conn->commit();

    echo json_encode([
        "success"=>true,
        "message"=>"Student deleted successfully."
    ]);

}
catch(PDOException $e){

    $conn->rollBack();

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);

}