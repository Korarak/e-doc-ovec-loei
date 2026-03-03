<?php
ob_start(); // Capture ALL output to prevent HTML warnings from breaking JSON
session_start();
include('edoc-db.php');

if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['action']) && $_POST['action'] === 'draw') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    } else {
        header("Location: index.php");
    }
    exit();
}

$user_id = $_SESSION['user_id'];
$uploadDir = 'uploads/sign/';
if (!file_exists($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

$action = $_POST['action'] ?? '';

// Helper to check and update primary if none exists
function ensurePrimarySignature($conn, $user_id) {
    // Check if any primary signature exists
    $stmt = $conn->prepare("SELECT sig_id, sign_path FROM user_signatures WHERE user_id = ? AND is_primary = 1 LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // No primary found. Get the latest added signature.
        $stmt_latest = $conn->prepare("SELECT sig_id, sign_path FROM user_signatures WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt_latest->bind_param("i", $user_id);
        $stmt_latest->execute();
        $latest = $stmt_latest->get_result();
        
        if ($latest->num_rows > 0) {
            $row = $latest->fetch_assoc();
            $new_primary_id = $row['sig_id'];
            $new_primary_path = $row['sign_path'];
            
            // Set it as primary
            $conn->query("UPDATE user_signatures SET is_primary = 1 WHERE sig_id = $new_primary_id");
            
            // Update user cache
            $stmt_update_user = $conn->prepare("UPDATE user SET sign = ? WHERE user_id = ?");
            $stmt_update_user->bind_param("si", $new_primary_path, $user_id);
            $stmt_update_user->execute();
            $_SESSION['sign'] = $new_primary_path;
        } else {
            // Nullify user cache if NO signatures exist at all
            $conn->query("UPDATE user SET sign = NULL WHERE user_id = $user_id");
            unset($_SESSION['sign']);
        }
        $stmt_latest->close();
    }
    $stmt->close();
}

if ($action === 'draw') {
    try {
        if (!isset($_POST['signature_data'])) {
            throw new Exception('No signature data provided');
        }

        $data_uri = str_replace(' ', '+', $_POST['signature_data']);
        $parts = explode(",", $data_uri);
        
        if (!isset($parts[1])) {
            throw new Exception('Invalid image format');
        }
        
        $encoded_image = $parts[1];
        $decoded_image = base64_decode($encoded_image);
        
        if ($decoded_image === false) {
            throw new Exception('Base64 decode failed');
        }
        
        $fileName = uniqid('sign_', true) . '.png';
        $targetFilePath = $uploadDir . $fileName;
        
        // Suppress warnings on file write, throw exception if false
        if (@file_put_contents($targetFilePath, $decoded_image) === false) {
            $error = error_get_last();
            $msg = $error ? $error['message'] : 'Failed to write file. Check directory permissions.';
            throw new Exception($msg);
        }

        // Add to user_signatures
        $stmt = $conn->prepare("INSERT INTO user_signatures (user_id, sign_path, is_primary) VALUES (?, ?, 0)");
        if (!$stmt) throw new Exception('Database prepare failed: ' . $conn->error);
        
        $stmt->bind_param("is", $user_id, $targetFilePath);
        if (!$stmt->execute()) {
            throw new Exception('Database execute failed: ' . $stmt->error);
        }
        $stmt->close();
        
        ensurePrimarySignature($conn, $user_id);
        
        ob_clean(); // Wipe any warnings that might have happened
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'path' => $targetFilePath]);
        exit();

    } catch (Exception $e) {
        ob_clean(); // Wipe the buffer so HTML errors don't leak
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
} elseif ($action === 'upload') {
    if (isset($_FILES['sign_file']) && $_FILES['sign_file']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        $fileMimeType = mime_content_type($_FILES['sign_file']['tmp_name']);
        
        if (in_array($fileMimeType, $allowedTypes)) {
            $extension = pathinfo($_FILES['sign_file']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('sign_', true) . '.' . $extension;
            $targetFilePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['sign_file']['tmp_name'], $targetFilePath)) {
                 $stmt = $conn->prepare("INSERT INTO user_signatures (user_id, sign_path, is_primary) VALUES (?, ?, 0)");
                 $stmt->bind_param("is", $user_id, $targetFilePath);
                 $stmt->execute();
                 $stmt->close();
                 
                 ensurePrimarySignature($conn, $user_id);
                 
                 header("Location: user_signature.php?msg=success");
                 exit();
            } else {
                 header("Location: user_signature.php?msg=error&reason=move_failed");
                 exit();
            }
        } else {
             header("Location: user_signature.php?msg=error&reason=invalid_type");
             exit();
        }
    }

} elseif ($action === 'set_primary') {
    $sig_id = intval($_POST['sig_id']);
    
    // Validate ownership
    $stmt = $conn->prepare("SELECT sign_path FROM user_signatures WHERE sig_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $sig_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $path = $row['sign_path'];
        
        // Reset all to 0
        $stmt_reset = $conn->prepare("UPDATE user_signatures SET is_primary = 0 WHERE user_id = ?");
        $stmt_reset->bind_param("i", $user_id);
        $stmt_reset->execute();
        
        // Set new primary
        $stmt_set = $conn->prepare("UPDATE user_signatures SET is_primary = 1 WHERE sig_id = ?");
        $stmt_set->bind_param("i", $sig_id);
        $stmt_set->execute();
        
        // Update user table cache
        $stmt_update_user = $conn->prepare("UPDATE user SET sign = ? WHERE user_id = ?");
        $stmt_update_user->bind_param("si", $path, $user_id);
        $stmt_update_user->execute();
        
        $_SESSION['sign'] = $path;
    }
    $stmt->close();
    header("Location: user_signature.php?msg=success");
    exit();

} elseif ($action === 'delete') {
    $sig_id = intval($_POST['sig_id']);
    
    // Validate ownership
    $stmt = $conn->prepare("SELECT sign_path FROM user_signatures WHERE sig_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $sig_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Delete file if it exists
        if (file_exists($row['sign_path'])) {
            unlink($row['sign_path']);
        }
        
        // Delete record
        $stmt_del = $conn->prepare("DELETE FROM user_signatures WHERE sig_id = ?");
        $stmt_del->bind_param("i", $sig_id);
        $stmt_del->execute();
        
        // Ensure another primary exists if we just deleted the primary
        ensurePrimarySignature($conn, $user_id);
    }
    $stmt->close();
    header("Location: user_signature.php?msg=success");
    exit();
}

header("Location: user_signature.php");
exit();
?>
