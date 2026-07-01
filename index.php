<?php

require_once "config/db.php";

echo json_encode([
    "success" => true,
    "message" => "FaceTrack AI PHP backend is running",
    "backend" => "PHP + MySQL",
    "database" => "facetrack_ai",
    "port" => "3307"
]);