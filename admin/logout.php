<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if($_SERVER['REQUEST_METHOD']=="OPTIONS"){
    exit();
}

echo json_encode([
    "success"=>true,
    "message"=>"Logout Successful"
]);