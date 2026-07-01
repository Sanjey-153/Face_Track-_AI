<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
    exit();
}

require_once("../config/db.php");

try {

    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data['id'];

    $roll_no = trim($data['roll_no']);
    $name = trim($data['name']);
    $department = trim($data['department']);
    $email = trim($data['email']);

    // Roll number uniqueness
    $stmt = $conn->prepare("
        SELECT id
        FROM students
        WHERE roll_no=?
        AND id<>?
    ");

    $stmt->execute([$roll_no, $id]);

    if ($stmt->rowCount() > 0) {

        echo json_encode([
            "success"=>false,
            "message"=>"Roll Number already exists."
        ]);

        exit();

    }

    // Email uniqueness
    $stmt = $conn->prepare("
        SELECT id
        FROM students
        WHERE email=?
        AND id<>?
    ");

    $stmt->execute([$email,$id]);

    if($stmt->rowCount()>0){

        echo json_encode([
            "success"=>false,
            "message"=>"Email already exists."
        ]);

        exit();

    }

    $stmt=$conn->prepare("
        UPDATE students
        SET
            roll_no=?,
            name=?,
            department=?,
            email=?
        WHERE id=?
    ");

    $stmt->execute([
        $roll_no,
        $name,
        $department,
        $email,
        $id
    ]);

    echo json_encode([
        "success"=>true,
        "message"=>"Student updated successfully."
    ]);

}
catch(PDOException $e){

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);

}