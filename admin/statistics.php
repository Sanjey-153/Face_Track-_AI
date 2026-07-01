<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if($_SERVER['REQUEST_METHOD']=="OPTIONS"){
    exit();
}

require_once("../config/db.php");

try{

    $students=$conn->query("SELECT COUNT(*) FROM students")->fetchColumn();

    $faculty=$conn->query("SELECT COUNT(*) FROM faculty")->fetchColumn();

    $reports=$conn->query("SELECT COUNT(*) FROM attendance_reports")->fetchColumn();

    $faces=$conn->query("SELECT COUNT(*) FROM student_face_embeddings")->fetchColumn();

    $present=$conn->query("
        SELECT COUNT(*)
        FROM attendance_report_students
        WHERE LOWER(attendance_status)='present'
    ")->fetchColumn();

    $absent=$conn->query("
        SELECT COUNT(*)
        FROM attendance_report_students
        WHERE LOWER(attendance_status)='absent'
    ")->fetchColumn();

    $rate=0;

    if(($present+$absent)>0){
        $rate=round(($present/($present+$absent))*100,2);
    }

    echo json_encode([

        "success"=>true,

        "statistics"=>[

            "total_students"=>$students,

            "total_faculty"=>$faculty,

            "attendance_reports"=>$reports,

            "registered_faces"=>$faces,

            "present_students"=>$present,

            "absent_students"=>$absent,

            "attendance_percentage"=>$rate
        ]
    ]);

}
catch(PDOException $e){

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);

}