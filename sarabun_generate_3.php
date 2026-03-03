<?php
session_start();
include('edoc-db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect input data and sanitize
    $doc_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : null;
    $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : null;
/*     $dep_id = isset($_POST['dep-id']) ? intval($_POST['dep-id']) : null;
    echo $dep_id;
    //exit(); */
    $input_text = isset($_POST['input-text']) ? trim($_POST['input-text']) : '';
    $x_pos = isset($_POST['x-pos']) ? intval($_POST['x-pos']) : 0;
    $y_pos = isset($_POST['y-pos']) ? intval($_POST['y-pos']) : 0;
    $page_num = isset($_POST['page-num']) ? intval($_POST['page-num']) : 1;
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $signatureDisplay = isset($_POST['sign_display']) ? $_POST['sign_display'] : 'False';
    $custom_sign_base64 = isset($_POST['custom_sign_base64']) ? $_POST['custom_sign_base64'] : '';

    $custom_sign_path = isset($_POST['custom_sign_path']) ? $_POST['custom_sign_path'] : '';

    $signatureImage = null;

    if ($signatureDisplay == 'True') {
        if (!empty($custom_sign_base64)) {
            // decode the custom base64 string
            $uploadDir = 'uploads/sign/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $encoded_image = explode(",", $custom_sign_base64)[1];
            $decoded_image = base64_decode($encoded_image);
            $fileName = uniqid('temp_sign_', true) . '.png';
            $targetFilePath = $uploadDir . $fileName;
            
            if (file_put_contents($targetFilePath, $decoded_image)) {
                $signatureImage = $targetFilePath;
            } else {
                $signatureImage = isset($_SESSION['sign']) ? $_SESSION['sign'] : null;
            }
        } elseif (!empty($custom_sign_path)) {
            // use selected gallery signature
            $signatureImage = $custom_sign_path;
        } else {
            // use normal signature
            $signatureImage = isset($_SESSION['sign']) ? $_SESSION['sign'] : null;
        }
    }


    // Check required fields
    if (!$doc_id || !$file_id || !$user_id) {
        die('Missing required fields.');
    }

    $signer_role = isset($_POST['signer_role']) ? $_POST['signer_role'] : 'codirector';

    // Check if doc_id already exists in sign_doc
    $stmt = $conn->prepare("SELECT sign_doc_id FROM sign_doc WHERE doc_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // If doc_id exists, fetch the sign_doc_id
        $stmt->bind_result($sign_doc_id);
        $stmt->fetch();
        
        if ($signer_role === 'director') {
            $updateSql = "UPDATE sign_doc SET sign_director = 'approve' WHERE sign_doc_id = ?";
        } else {
            $updateSql = "UPDATE sign_doc SET sign_codirector = 'approve' WHERE sign_doc_id = ?";
        }
        
        $stmt_up = $conn->prepare($updateSql);
        $stmt_up->bind_param('i', $sign_doc_id);
        $stmt_up->execute();
        $stmt_up->close();
    } else {
        // If doc_id does not exist, insert a new record in sign_doc
        $stmt->close();
        
        $sign_sarabun = 'pending';
        $sign_codirector = ($signer_role === 'codirector') ? 'approve' : 'pending';
        $sign_director = ($signer_role === 'director') ? 'approve' : 'pending';
        
        $stmt = $conn->prepare("INSERT INTO sign_doc (doc_id, user_id, doc_status, sign_sarabun, sign_codirector, sign_director) VALUES (?, ?, 'approve', ?, ?, ?)");
        $stmt->bind_param("iisss", $doc_id, $user_id, $sign_sarabun, $sign_codirector, $sign_director);
        $stmt->execute();

        if ($stmt->error) {
            die('Error inserting into sign_doc: ' . $stmt->error);
        }

        $sign_doc_id = $conn->insert_id;
    }
    if ($stmt) $stmt->close();

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

    $signer_role = isset($_POST['signer_role']) ? $_POST['signer_role'] : 'codirector';
    
    // Redirect to manage page
    if ($signer_role === 'director') {
        header("Location: director_manage.php");
    } else {
        header("Location: codirector_manage.php");
    }
    exit();
} else {
    // Redirect to form page if not POST
    header("Location: sarabun_form.php");
    exit();
}
?>