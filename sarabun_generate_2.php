<?php
session_start();
include('edoc-db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect input data and sanitize
    $doc_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : null;
    $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : null;
    $dep_id = isset($_POST['dep-id']) ? intval($_POST['dep-id']) : null;
    $input_text = isset($_POST['input-text']) ? trim($_POST['input-text']) : '';
    $x_pos = isset($_POST['x-pos']) ? intval($_POST['x-pos']) : 0;
    $y_pos = isset($_POST['y-pos']) ? intval($_POST['y-pos']) : 0;
    $page_num = isset($_POST['page-num']) ? intval($_POST['page-num']) : 1;
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $signatureDisplay = isset($_POST['sign_display']) ? $_POST['sign_display'] : 'False';

                // Check if the signature display value is "True"
                if ($signatureDisplay == 'True') {
                    $signatureImage = isset($_SESSION['sign']) ? $_SESSION['sign'] : null;
                } else {
                    $signatureImage = null; // Ensure no signature image is used if not set to "True"
                }



    // Check required fields
    if (!$doc_id || !$file_id || !$user_id) {
        die('Missing required fields.');
    }

    // Check if doc_id already exists in sign_doc
    $stmt = $conn->prepare("SELECT sign_doc_id FROM sign_doc WHERE doc_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // ถ้ามี sign_doc อยู่แล้ว อัปเดต dep_id และ sign_sarabun
        $stmt->bind_result($sign_doc_id);
        $stmt->fetch();
        $sign_sarabun = 'approve';
        $updateSql = "UPDATE sign_doc SET dep_id = ?, sign_sarabun = ? WHERE sign_doc_id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param('isi', $dep_id, $sign_sarabun, $sign_doc_id);
        $stmt->execute();
    } else {
        // ถ้ายังไม่มี sign_doc สร้างใหม่พร้อม dep_id และ sign_sarabun=approve
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO sign_doc (doc_id, user_id, dep_id, doc_status, sign_sarabun, sign_codirector, sign_director) VALUES (?, ?, ?, 'approve', 'approve', 'pending', 'pending')");
        $stmt->bind_param("iii", $doc_id, $user_id, $dep_id);
        $stmt->execute();

        if ($stmt->error) {
            die('Error inserting into sign_doc: ' . $stmt->error);
        }

        $sign_doc_id = $conn->insert_id;
    }
    $stmt->close();

    // Insert data into sign_detail table
    $sign_datetime = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO sign_detail (sign_doc_id, sign_file_id, sign_txt, sign_pic, sign_by, sign_datetime, x_pos, y_pos, page_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissssiii", $sign_doc_id, $file_id, $input_text,$signatureImage, $user_id, $sign_datetime, $x_pos, $y_pos, $page_num);
    $stmt->execute();

    if ($stmt->error) {
        die('Error inserting into sign_detail: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    // Redirect to manage page
    header("Location: sarabun_manage.php");
    exit();
} else {
    // Redirect to form page if not POST
    header("Location: sarabun_form.php");
    exit();
}
?>