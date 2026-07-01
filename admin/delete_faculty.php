<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if($_SERVER['REQUEST_METHOD']=="OPTIONS"){
    exit();
}

require_once("../config/db.php");

try{

    $data=json_decode(file_get_contents("php://input"),true);

    $id=$data['id'];

    $conn->beginTransaction();

    $stmt=$conn->prepare("
        DELETE FROM faculty
        WHERE id=?
    ");

    $stmt->execute([$id]);

    $conn->commit();

    echo json_encode([
        "success"=>true,
        "message"=>"Faculty deleted successfully."
    ]);

}
catch(PDOException $e){

    if($conn->inTransaction()){
        $conn->rollBack();
    }

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);

}