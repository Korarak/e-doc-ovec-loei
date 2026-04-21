<?php
session_start();
include('edoc-db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_id    = intval($_POST['doc_id']);
    $file_id   = intval($_POST['file_id']);
    $input_text = trim($_POST['input-text']);
    $x_pos     = $_POST['x-pos'];
    $y_pos     = $_POST['y-pos'];
    $dep_ids   = isset($_POST['dep-id']) ? (array)$_POST['dep-id'] : [null];
    $page_num  = intval($_POST['page-num']);
    $user_id   = $_SESSION['user_id'];
    $sign_datetime = date('Y-m-d H:i:s');

    foreach ($dep_ids as $dep_id) {
        $curr_dep_id = ($dep_id !== null) ? intval($dep_id) : null;
        
        // ตรวจสอบว่า doc_id และ dep_id นี้มีอยู่แล้วใน sign_doc หรือไม่
        if ($curr_dep_id !== null) {
            $stmt = $conn->prepare("SELECT sign_doc_id FROM sign_doc WHERE doc_id = ? AND dep_id = ?");
            $stmt->bind_param("ii", $doc_id, $curr_dep_id);
        } else {
            $stmt = $conn->prepare("SELECT sign_doc_id FROM sign_doc WHERE doc_id = ? AND dep_id IS NULL");
            $stmt->bind_param("i", $doc_id);
        }
        
        $stmt->execute();
        $stmt->store_result();
        $sign_doc_id = null;

        if ($stmt->num_rows > 0) {
            // มีอยู่แล้ว → อัปเดต sign_sarabun
            $stmt->bind_result($target_sign_doc_id);
            $stmt->fetch();
            $stmt->close();
            $sign_doc_id = $target_sign_doc_id;

            $upStmt = $conn->prepare("UPDATE sign_doc SET sign_sarabun = 'stamp_done' WHERE sign_doc_id = ?");
            $upStmt->bind_param("i", $sign_doc_id);
            $upStmt->execute();
            $upStmt->close();
        } else {
            // ยังไม่มี → INSERT ใหม่
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO sign_doc (doc_id, user_id, dep_id, doc_status, sign_sarabun, sign_codirector, sign_director) VALUES (?, ?, ?, 'pending', 'stamp_done', 'pending', 'pending')");
            $stmt->bind_param("iii", $doc_id, $user_id, $curr_dep_id);
            $stmt->execute();
            $sign_doc_id = $conn->insert_id;
            $stmt->close();
        }

        // บันทึกข้อมูลใน sign_detail (สำหรับทุก sign_doc_id)
        $stmt = $conn->prepare("INSERT INTO sign_detail (sign_doc_id, sign_file_id, sign_txt, sign_pic, sign_by, sign_datetime, x_pos, y_pos, page_num) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssddi", $sign_doc_id, $file_id, $input_text, $user_id, $sign_datetime, $x_pos, $y_pos, $page_num);
        $stmt->execute();
        $stmt->close();
    }

    $conn->close();

    header("Location: sarabun_manage.php");
    exit();
}
?>
