<?php
// auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ป้องกัน CSRF
 */
function check_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=csrf_error");
            exit();
        }
    }
}

/**
 * ตรวจสอบการเข้าสู่ระบบ
 */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['inst_id'])) {
    header("Location: index.php");
    exit();
}

/**
 * ฟังก์ชันตรวจสอบสิทธิ์การเข้าถึงหน้าต่างๆ ตาม role_id
 * @param array $allowed_roles อาเรย์ของ role_id ที่อนุญาต เช่น [1, 2] (Admin, สารบรรณ)
 */
function require_role($allowed_roles = []) {
    if (!isset($_SESSION['role_id'])) {
        header("Location: dashboard.php?error=unauthorized");
        exit();
    }
    
    $role_id = (int)$_SESSION['role_id'];
    
    // Super Admin (99) bypass everything in superadmin pages but handled separately
    if ($role_id === 99) {
        return; // Superadmin has all access
    }
    
    // ถ้าไม่ได้อยู่ใน array ที่ได้รับอนุญาต
    if (!empty($allowed_roles) && !in_array($role_id, $allowed_roles)) {
        header("Location: dashboard.php?error=unauthorized");
        exit();
    }
}

/**
 * ฟังก์ชันตรวจสอบสิทธิ์สำหรับ Super Admin เท่านั้น
 */
function require_superadmin() {
    if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 99) {
        header("Location: dashboard.php?error=requires_superadmin");
        exit();
    }
}

/**
 * ฟังก์ชันช่วยเหลือ: ตรวจสอบว่าเป็นผู้อำนวยการหรือไม่ (ID ตำแหน่ง = 1)
 */
function is_director($conn, $user_id) {
    if (!$user_id) return false;
    // Cache the result in session to avoid repeated DB calls
    if (isset($_SESSION['is_director'])) return $_SESSION['is_director'];
    
    $stmt = $conn->prepare("SELECT position_id FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $_SESSION['is_director'] = ($result && (int)$result['position_id'] === 1);
    return $_SESSION['is_director'];
}

/**
 * ฟังก์ชันช่วยเหลือ: ตรวจสอบว่าเป็นรองผู้อำนวยการหรือไม่ (ID ตำแหน่ง = 2)
 */
function is_codirector($conn, $user_id) {
    if (!$user_id) return false;
    if (isset($_SESSION['is_codirector'])) return $_SESSION['is_codirector'];
    
    $stmt = $conn->prepare("SELECT position_id FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $_SESSION['is_codirector'] = ($result && (int)$result['position_id'] === 2);
    return $_SESSION['is_codirector'];
}

/**
 * ฟังก์ชันช่วยเหลือ: ตรวจสอบว่าเป็นงานสารบรรณหรือไม่ (ตรวจสอบจากชื่อฝ่ายที่มีคำว่า 'สารบรรณ')
 */
function is_sarabun($conn, $user_id) {
    if (!$user_id) return false;
    if (isset($_SESSION['is_sarabun'])) return $_SESSION['is_sarabun'];
    
    $stmt = $conn->prepare("
        SELECT d.dep_name 
        FROM user u 
        JOIN department d ON u.dep_id = d.dep_id 
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $_SESSION['is_sarabun'] = ($result && strpos($result['dep_name'], 'สารบรรณ') !== false);
    return $_SESSION['is_sarabun'];
}
?>
