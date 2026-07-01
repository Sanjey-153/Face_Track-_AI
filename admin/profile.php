<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

require_once("../config/db.php");

try {

    if ($_SERVER['REQUEST_METHOD'] == "GET") {

        $adminId = $_GET['admin_id'] ?? "";

        if ($adminId == "") {
            echo json_encode([
                "success"=>false,
                "message"=>"Admin ID is required."
            ]);
            exit();
        }

        $stmt = $conn->prepare("
            SELECT
                id,
                admin_id,
                username,
                full_name,
                email,
                phone,
                role,
                status,
                created_at
            FROM admin
            WHERE admin_id=?
        ");

        $stmt->execute([$adminId]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$admin){
            echo json_encode([
                "success"=>false,
                "message"=>"Admin not found."
            ]);
            exit();
        }

        echo json_encode([
            "success"=>true,
            "profile"=>$admin
        ]);
    }

    if ($_SERVER['REQUEST_METHOD']=="POST") {

        $data=json_decode(file_get_contents("php://input"),true);

        $stmt=$conn->prepare("
            UPDATE admin
            SET
            full_name=?,
            email=?,
            phone=?
            WHERE admin_id=?
        ");

        $stmt->execute([
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $data['admin_id']
        ]);

        echo json_encode([
            "success"=>true,
            "message"=>"Profile updated successfully."
        ]);
    }

}
catch(PDOException $e){

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);

}