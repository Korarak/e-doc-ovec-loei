<?php
session_start();
include('edoc-db.php');

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$detail_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$doc_id = isset($_GET['doc_id']) ? intval($_GET['doc_id']) : null;
$file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : null;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

if ($detail_id) {
    // Optional: Check if the user is allowed to delete this (e.g., owner or admin)
    $stmt = $conn->prepare("DELETE FROM sign_detail WHERE detail_id = ?");
    $stmt->bind_param("i", $detail_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

// Redirect back to the referring page
if (isset($_SERVER['HTTP_REFERER'])) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
} else {
    header("Location: dashboard.php");
}
exit();
?>