<?php
session_start();
include('edoc-db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_id    = intval($_POST['doc_id']);
    $file_id   = intval($_POST['file_id']);
    $input_text = trim($_POST['input-text']);
    $x_pos     = $_POST['x-pos'];
    $y_pos     = $_POST['y-pos'];
    $dep_id    = isset($_POST['dep-id']) ? intval($_POST['dep-id']) : null;
    $page_num  = intval($_POST['page-num']);
    $user_id   = $_SESSION['user_id'];
    $sign_datetime = date('Y-m-d H:i:s');

    // ตรวจสอบว่า doc_id มีอยู่แล้วใน sign_doc หรือไม่
    $stmt = $conn->prepare("SELECT sign_doc_id FROM sign_doc WHERE doc_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // มีอยู่แล้ว → อัปเดต dep_id และ sign_sarabun
        $stmt->bind_result($sign_doc_id);
        $stmt->fetch();
        $stmt->close();

        $upStmt = $conn->prepare("UPDATE sign_doc SET dep_id = ?, sign_sarabun = 'approve' WHERE sign_doc_id = ?");
        $upStmt->bind_param("ii", $dep_id, $sign_doc_id);
        $upStmt->execute();
        $upStmt->close();
    } else {
        // ยังไม่มี → INSERT ใหม่พร้อม dep_id และ sign_sarabun=approve
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO sign_doc (doc_id, user_id, dep_id, doc_status, sign_sarabun, sign_codirector, sign_director) VALUES (?, ?, ?, 'approve', 'approve', 'pending', 'pending')");
        $stmt->bind_param("iii", $doc_id, $user_id, $dep_id);
        $stmt->execute();
        $sign_doc_id = $conn->insert_id;
        $stmt->close();
    }

    // บันทึกข้อมูลใน sign_detail
    $stmt = $conn->prepare("INSERT INTO sign_detail (sign_doc_id, sign_file_id, sign_txt, sign_pic, sign_by, sign_datetime, x_pos, y_pos, page_num) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssddi", $sign_doc_id, $file_id, $input_text, $user_id, $sign_datetime, $x_pos, $y_pos, $page_num);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header("Location: sarabun_manage.php");
    exit();
}
?>
