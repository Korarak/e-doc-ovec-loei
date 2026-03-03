<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'edoc-db.php';

if (isset($_GET['file_id']) && isset($_GET['doc_id'])) {
    $file_id = intval($_GET['file_id']);
    $doc_id = intval($_GET['doc_id']);

    // Fetch the file path from the database
    $fileSql = "SELECT file_path FROM document_files WHERE file_id = $file_id AND doc_id = $doc_id";
    $fileResult = $conn->query($fileSql);

    if ($fileResult->num_rows > 0) {
        $fileRow = $fileResult->fetch_assoc();
        $filePath = $fileRow['file_path'];

        // Delete the file from the file system
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete the file record from the database
        $deleteSql = "DELETE FROM document_files WHERE file_id = $file_id AND doc_id = $doc_id";
        if ($conn->query($deleteSql) === TRUE) {
            $_SESSION['success'] = "File deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting file: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "File not found.";
    }
} else {
    $_SESSION['error'] = "Invalid request.";
}

header("Location: doc_edit.php?id=$doc_id");
exit();
?>
