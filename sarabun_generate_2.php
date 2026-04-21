<?php
session_start();
include('edoc-db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect input data and sanitize
    $doc_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : null;
    $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : null;
    $dep_ids = isset($_POST['dep-id']) ? (array)$_POST['dep-id'] : [null];
    $input_text = isset($_POST['input-text']) ? trim($_POST['input-text']) : '';
    $x_pos = isset($_POST['x-pos']) ? intval($_POST['x-pos']) : 0;
    $y_pos = isset($_POST['y-pos']) ? intval($_POST['y-pos']) : 0;
    $page_num = isset($_POST['page-num']) ? intval($_POST['page-num']) : 1;
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $signatureDisplay = isset($_POST['sign_display']) ? $_POST['sign_display'] : 'False';

    if ($signatureDisplay == 'True') {
        $signatureImage = isset($_SESSION['sign']) ? $_SESSION['sign'] : null;
    } else {
        $signatureImage = null;
    }

    if (!$doc_id || !$file_id || !$user_id) {
        die('Missing required fields.');
    }

    $sign_datetime = date('Y-m-d H:i:s');

    foreach ($dep_ids as $dep_id) {
        $curr_dep_id = ($dep_id !== null) ? intval($dep_id) : null;

        // Check if doc_id and dep_id already exists in sign_doc
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
            $stmt->bind_result($target_sign_doc_id);
            $stmt->fetch();
            $sign_doc_id = $target_sign_doc_id;

            $updateSql = "UPDATE sign_doc SET sign_sarabun = 'approve', doc_status = 'approve' WHERE sign_doc_id = ?";
            $upStmt = $conn->prepare($updateSql);
            $upStmt->bind_param('i', $sign_doc_id);
            $upStmt->execute();
            $upStmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO sign_doc (doc_id, user_id, dep_id, doc_status, sign_sarabun, sign_codirector, sign_director) VALUES (?, ?, ?, 'approve', 'approve', 'pending', 'pending')");
            $stmt->bind_param("iii", $doc_id, $user_id, $curr_dep_id);
            $stmt->execute();
            $sign_doc_id = $conn->insert_id;
        }
        $stmt->close();

        // Insert into sign_detail
        $stmt = $conn->prepare("INSERT INTO sign_detail (sign_doc_id, sign_file_id, sign_txt, sign_pic, sign_by, sign_datetime, x_pos, y_pos, page_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssiii", $sign_doc_id, $file_id, $input_text, $signatureImage, $user_id, $sign_datetime, $x_pos, $y_pos, $page_num);
        $stmt->execute();
        $stmt->close();
    }

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