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

    $faculty_id=trim($data['faculty_id']);
    $name=trim($data['name']);
    $email=trim($data['email']);
    $subject=trim($data['subject']);
    $designation=trim($data['designation']);

    $stmt=$conn->prepare("SELECT id FROM faculty WHERE faculty_id=?");
    $stmt->execute([$faculty_id]);

    if($stmt->rowCount()>0){

        echo json_encode([
            "success"=>false,
            "message"=>"Faculty ID already exists."
        ]);

        exit();
    }

    $stmt=$conn->prepare("SELECT id FROM faculty WHERE email=?");
    $stmt->execute([$email]);

    if($stmt->rowCount()>0){

        echo json_encode([
            "success"=>false,
            "message"=>"Email already exists."
        ]);

        exit();
    }

    $stmt=$conn->prepare("
        INSERT INTO faculty
        (
            faculty_id,
            name,
            email,
            subject,
            designation
        )
        VALUES
        (?,?,?,?,?)
    ");

    $stmt->execute([
        $faculty_id,
        $name,
        $email,
        $subject,
        $designation
    ]);

    echo json_encode([
        "success"=>true,
        "message"=>"Faculty added successfully."
    ]);

}
catch(PDOException $e){

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);

}