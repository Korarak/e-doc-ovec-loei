<?php
session_start();
include 'edoc-db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // ดึง user ตาม username ก่อน แล้วค่อย verify password ด้วย bcrypt
    $sql = "SELECT u.*, i.inst_name FROM user u LEFT JOIN institution i ON u.inst_id = i.inst_id WHERE u.username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // รองรับทั้ง bcrypt (ใหม่) และ MD5 (เก่า)
        $validPassword = false;
        if (password_verify($password, $row['password'])) {
            $validPassword = true;
        } elseif ($row['password'] === md5($password)) {
            // Legacy MD5 – อัปเกรดเป็น bcrypt ทันที
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $upStmt = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
            $upStmt->bind_param("si", $newHash, $row['user_id']);
            $upStmt->execute();
            $upStmt->close();
            $validPassword = true;
        }

        if ($validPassword) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $row['user_id'];
            $_SESSION['inst_id']    = $row['inst_id'];
            $_SESSION['inst_name']  = $row['inst_name'] ?? '';
            $_SESSION['username']   = $row['username'];
            $_SESSION['fullname']   = $row['fullname'];
            $_SESSION['sign']       = $row['sign'];
            $_SESSION['position_id']= $row['position_id'];
            $_SESSION['role_id']    = $row['role_id'];
            $_SESSION['dep_id']     = $row['dep_id'];
            if ((int)$row['role_id'] === 99) {
                header("Location: superadmin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        }
    }
    $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - LoeiTech E-sign System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Kanit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-vh-100 min-h-screen">
    
    <div class="w-full max-w-md p-8 m-4 bg-white rounded-2xl shadow-xl ring-1 ring-gray-200">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-brand-50 mb-4">
                <svg class="w-8 h-8 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">E-Sign System</h2>
            <p class="text-gray-500 mt-2 text-sm">ลงชื่อเข้าใช้ระบบสารบรรณอิเล็กทรอนิกส์</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg flex items-center" role="alert">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="post" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">ชื่อผู้ใช้งาน</label>
                <input type="text" id="username" name="username" required 
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all"
                    placeholder="กรอกชื่อผู้ใช้งาน">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่าน</label>
                <input type="password" id="password" name="password" required 
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all"
                    placeholder="กรอกรหัสผ่าน">
            </div>

            <button type="submit" 
                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 transition-colors mt-6">
                เข้าสู่ระบบ
            </button>
        </form>
    </div>

</body>
</html>
