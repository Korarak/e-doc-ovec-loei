<?php
ob_start();
require_once 'auth_check.php';
// Allow Admin (1) Only for User Management
require_role([1]);

include 'edoc-db.php';

// Function to handle different actions
function handleAction($conn) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        try {
            if (isset($_POST['add'])) {
                $inst_id     = $_SESSION['inst_id'];
                $username    = trim($_POST['username']);
                $password    = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $fullname    = trim($_POST['fullname']);
                $custom_base64 = isset($_POST['custom_sign_base64']) ? $_POST['custom_sign_base64'] : null;
                $sign        = uploadSign($custom_base64);
                $position_id = intval($_POST['position_id']);
                $role_id     = intval($_POST['role_id']);
                $dep_id      = intval($_POST['dep_id']);

                $sql = "INSERT INTO user (inst_id, username, password, fullname, sign, position_id, role_id, dep_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issssiii", $inst_id, $username, $password, $fullname, $sign, $position_id, $role_id, $dep_id);
                if ($stmt->execute()) {
                    $new_user_id = $conn->insert_id;
                    if ($sign !== null) {
                        $sql_sig = "INSERT INTO user_signatures (user_id, sign_path, is_primary) VALUES (?, ?, 1)";
                        $stmt_sig = $conn->prepare($sql_sig);
                        $stmt_sig->bind_param("is", $new_user_id, $sign);
                        $stmt_sig->execute();
                    }
                    header("Location: user_manage.php?msg=success");
                    exit();
                } else {
                    header("Location: user_manage.php?error=db_error");
                    exit();
                }
            } elseif (isset($_POST['edit'])) {
                $user_id     = intval($_POST['user_id']);
                $username    = trim($_POST['username']);
                $fullname    = trim($_POST['fullname']);
                $custom_base64 = isset($_POST['custom_sign_base64']) ? $_POST['custom_sign_base64'] : null;
                $sign        = uploadSign($custom_base64);
                $position_id = intval($_POST['position_id']);
                $role_id     = intval($_POST['role_id']);
                $dep_id      = intval($_POST['dep_id']);

                $rawPassword = $_POST['password'];
                $inst_id     = $_SESSION['inst_id'];

                if (trim($rawPassword) !== '') {
                    $password = password_hash($rawPassword, PASSWORD_BCRYPT);
                    if ($sign === null) {
                        $sql  = "UPDATE user SET username=?, password=?, fullname=?, position_id=?, role_id=?, dep_id=? WHERE user_id=? AND inst_id=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sssiiiii", $username, $password, $fullname, $position_id, $role_id, $dep_id, $user_id, $inst_id);
                    } else {
                        $sql  = "UPDATE user SET username=?, password=?, fullname=?, sign=?, position_id=?, role_id=?, dep_id=? WHERE user_id=? AND inst_id=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssiiiii", $username, $password, $fullname, $sign, $position_id, $role_id, $dep_id, $user_id, $inst_id);
                    }
                } else {
                    if ($sign === null) {
                        $sql  = "UPDATE user SET username=?, fullname=?, position_id=?, role_id=?, dep_id=? WHERE user_id=? AND inst_id=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssiiiii", $username, $fullname, $position_id, $role_id, $dep_id, $user_id, $inst_id);
                    } else {
                        $sql  = "UPDATE user SET username=?, fullname=?, sign=?, position_id=?, role_id=?, dep_id=? WHERE user_id=? AND inst_id=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sssiiiii", $username, $fullname, $sign, $position_id, $role_id, $dep_id, $user_id, $inst_id);
                    }
                }
                if ($stmt->execute()) {
                    if ($sign !== null) {
                        $sql_sig = "INSERT INTO user_signatures (user_id, sign_path, is_primary) VALUES (?, ?, 1)";
                        $stmt_sig = $conn->prepare($sql_sig);
                        $stmt_sig->bind_param("is", $user_id, $sign);
                        $stmt_sig->execute();
                    }
                    header("Location: user_manage.php?msg=success");
                    exit();
                } else {
                    header("Location: user_manage.php?error=db_error");
                    exit();
                }
            } elseif (isset($_POST['delete'])) {
                $user_id = intval($_POST['user_id']);
                $inst_id = $_SESSION['inst_id'];
                try {
                    $sql = "DELETE FROM user WHERE user_id=? AND inst_id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $user_id, $inst_id);
                    if ($stmt->execute()) {
                        header("Location: user_manage.php?msg=success");
                        exit();
                    } else {
                        header("Location: user_manage.php?error=db_error");
                        exit();
                    }
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() == 1451) {
                        header("Location: user_manage.php?error=fk_error");
                    } else {
                        header("Location: user_manage.php?error=db_error");
                    }
                    exit();
                }
            }
        } catch (Exception $e) {
            header("Location: user_manage.php?error=db_error");
            exit();
        }
    }
}

function uploadSign($base64_data = null) {
    if (!empty($base64_data)) {
        $base64_data = str_replace(' ', '+', $base64_data);
        $parts = explode(",", $base64_data);
        if (isset($parts[1])) {
            $encoded_image = $parts[1];
            $decoded_image = base64_decode($encoded_image);
            $uploadDir = 'uploads/sign/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = uniqid('sign_', true) . '.png';
            $targetFilePath = $uploadDir . $fileName;
            if (file_put_contents($targetFilePath, $decoded_image)) {
                return $targetFilePath;
            }
        }
    }

    if (isset($_FILES['sign']) && $_FILES['sign']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        $fileMimeType = mime_content_type($_FILES['sign']['tmp_name']);
        if (!in_array($fileMimeType, $allowedTypes)) return null;
        $uploadDir = 'uploads/sign/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $extension = pathinfo($_FILES['sign']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('sign_', true) . '.' . $extension;
        $targetFilePath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['sign']['tmp_name'], $targetFilePath)) return $targetFilePath;
    }
    return null;
}

handleAction($conn);

$inst_id = $_SESSION['inst_id'];
$sql = "SELECT u.*, p.position_name, r.role_name, d.dep_name FROM user u
        LEFT JOIN `position` p ON u.position_id = p.position_id
        LEFT JOIN role r ON u.role_id = r.role_id
        LEFT JOIN department d ON u.dep_id = d.dep_id
        WHERE u.inst_id = ?
        ORDER BY u.user_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $inst_id);
$stmt->execute();
$result = $stmt->get_result();

function fetchOptions($conn, $table, $id_field, $name_field, $inst_id = null) {
    if ($inst_id && in_array($table, ['department', '`position`', 'position'])) {
        $tbl = str_replace('`', '', $table);
        $sql = "SELECT $id_field, $name_field FROM `$tbl` WHERE inst_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $inst_id);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $sql = "SELECT $id_field, $name_field FROM $table";
        $res = $conn->query($sql);
    }
    $options = [];
    while ($row = $res->fetch_assoc()) $options[$row[$id_field]] = $row[$name_field];
    return $options;
}

$positions = fetchOptions($conn, 'position', 'position_id', 'position_name', $_SESSION['inst_id']);
$roles = fetchOptions($conn, 'role', 'role_id', 'role_name');
$departments = fetchOptions($conn, 'department', 'dep_id', 'dep_name', $_SESSION['inst_id']);
?>

<div class="py-2">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="bi bi-people-fill text-brand-600"></i> จัดการข้อมูลผู้ใช้ระบบ
        </h2>
        <button onclick="openModal('addUserModal')" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center gap-2 transition-all shadow-sm">
            <i class="bi bi-person-plus"></i> เพิ่มผู้ใช้ใหม่
        </button>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse datatable text-sm">
                    <thead class="bg-slate-800 text-white text-sm">
                        <tr>
                            <th class="px-4 py-3 font-medium rounded-tl-lg">ID</th>
                            <th class="px-4 py-3 font-medium">Username</th>
                            <th class="px-4 py-3 font-medium">ชื่อ-นามสกุล</th>
                            <th class="px-4 py-3 font-medium text-center">ลายเซ็น</th>
                            <th class="px-4 py-3 font-medium">ตำแหน่ง</th>
                            <th class="px-4 py-3 font-medium">สิทธิ์ (Role)</th>
                            <th class="px-4 py-3 font-medium">ฝ่ายงาน</th>
                            <th class="px-4 py-3 font-medium text-center rounded-tr-lg">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-4 py-3 text-gray-500"><?php echo $row['user_id']; ?></td>
                            <td class="px-4 py-3 font-medium text-gray-900"><?php echo htmlspecialchars($row['username']); ?></td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($row['sign'] && file_exists($row['sign'])): ?>
                                    <img src="<?php echo htmlspecialchars($row['sign']); ?>" alt="Sign" class="h-10 inline-block border border-gray-200 rounded shadow-sm">
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">ไม่มี</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($row['position_name'] ?? ''); ?></td>
                            <td class="px-4 py-3">
                                <?php
                                    $rName = htmlspecialchars($row['role_name'] ?? '');
                                    if ($row['role_id'] == 1) echo "<span class='inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-red-100 text-red-700'>{$rName}</span>";
                                    else echo "<span class='inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-700'>{$rName}</span>";
                                ?>
                            </td>
                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($row['dep_name'] ?? ''); ?></td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick="openModal('editModal<?php echo $row['user_id']; ?>')" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 rounded-lg transition-colors">
                                        <i class="bi bi-pencil"></i> แก้ไข
                                    </button>
                                    <form method="POST" id="form-delete-<?php echo $row['user_id']; ?>" class="inline">
                                        <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                        <input type="hidden" name="delete" value="1">
                                        <button type="button" onclick="confirmDeleteForm('form-delete-<?php echo $row['user_id']; ?>')" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 border border-red-200 hover:bg-red-100 rounded-lg transition-colors">
                                            <i class="bi bi-trash"></i> ลบ
                                        </button>
                                    </form>
                                </div>

                                <!-- Edit Modal -->
                                <div id="editModal<?php echo $row['user_id']; ?>" class="modal-overlay hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                                    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                                            <h5 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                                <i class="bi bi-pencil-square text-amber-500"></i> แก้ไข: <?php echo htmlspecialchars($row['username']); ?>
                                            </h5>
                                            <button onclick="closeModal('editModal<?php echo $row['user_id']; ?>')" class="text-gray-400 hover:text-gray-600">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="px-6 py-4 space-y-4 text-left">
                                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                                    <input type="text" name="username" value="<?php echo htmlspecialchars($row['username']); ?>" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-gray-400 text-xs">(เว้นว่างถ้าไม่เปลี่ยน)</span></label>
                                                    <input type="password" name="password" placeholder="••••••" autocomplete="new-password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ-นามสกุล</label>
                                                    <input type="text" name="fullname" value="<?php echo htmlspecialchars($row['fullname']); ?>" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">ลายเซ็น (PNG/JPG)</label>
                                                    
                                                    <!-- Signature Input Type Selection -->
                                                    <div class="flex items-center gap-4 mb-3">
                                                        <label class="inline-flex items-center">
                                                            <input type="radio" name="sign_input_type_<?= $row['user_id'] ?>" value="upload" checked class="form-radio text-brand-600 focus:ring-brand-500" onchange="toggleSignInput('edit', <?= $row['user_id'] ?>)">
                                                            <span class="ml-2 text-sm text-gray-700">อัปโหลดไฟล์</span>
                                                        </label>
                                                        <label class="inline-flex items-center">
                                                            <input type="radio" name="sign_input_type_<?= $row['user_id'] ?>" value="draw" class="form-radio text-brand-600 focus:ring-brand-500" onchange="toggleSignInput('edit', <?= $row['user_id'] ?>)">
                                                            <span class="ml-2 text-sm text-gray-700">วาดลายเซ็นสด</span>
                                                        </label>
                                                    </div>

                                                    <!-- Upload Container -->
                                                    <div id="upload_container_edit_<?= $row['user_id'] ?>">
                                                        <input type="file" name="sign" accept="image/png, image/jpeg" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                                                    </div>

                                                    <!-- Draw Container -->
                                                    <div id="draw_container_edit_<?= $row['user_id'] ?>" class="hidden">
                                                        <div class="border-2 border-dashed border-gray-300 rounded-xl bg-gray-50/50 p-3 text-center">
                                                            <div id="preview_sign_edit_<?= $row['user_id'] ?>" class="hidden mb-3">
                                                                <img src="" class="h-16 mx-auto border border-gray-200 bg-white rounded-lg shadow-sm">
                                                                <span class="text-xs text-green-600 mt-2 block"><i class="bi bi-check-circle"></i> ลายเซ็นใหม่พร้อมบันทึก</span>
                                                            </div>
                                                            <button type="button" onclick="openAdminDrawModal(<?= $row['user_id'] ?>)" class="bg-white border text-sm border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center gap-2 transition-colors">
                                                                <i class="bi bi-pen-fill text-brand-500"></i> เปิดกระดานวาดลายเซ็น
                                                            </button>
                                                            <input type="hidden" name="custom_sign_base64" id="custom_sign_base64_edit_<?= $row['user_id'] ?>" value="">
                                                        </div>
                                                    </div>

                                                    <?php if ($row['sign'] && file_exists($row['sign'])): ?>
                                                        <div class="mt-2 flex items-center gap-2"><span class="text-xs text-gray-400">ปัจจุบัน:</span> <img src="<?php echo htmlspecialchars($row['sign']); ?>" class="h-8"></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">ตำแหน่ง</label>
                                                        <select name="position_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none text-sm">
                                                            <?php foreach ($positions as $id => $name): ?>
                                                                <option value="<?php echo $id; ?>" <?php echo $id == $row['position_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">สิทธิ์</label>
                                                        <select name="role_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none text-sm">
                                                            <?php foreach ($roles as $id => $name): ?>
                                                                <option value="<?php echo $id; ?>" <?php echo $id == $row['role_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">ฝ่ายงาน</label>
                                                        <select name="dep_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none text-sm">
                                                            <?php foreach ($departments as $id => $name): ?>
                                                                <option value="<?php echo $id; ?>" <?php echo $id == $row['dep_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                                                <button type="button" onclick="closeModal('editModal<?php echo $row['user_id']; ?>')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">ยกเลิก</button>
                                                <button type="submit" name="edit" class="px-4 py-2 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition-colors shadow-sm">บันทึกการแก้ไข</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h5 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="bi bi-person-plus text-brand-600"></i> เพิ่มผู้ใช้ใหม่
            </h5>
            <button onclick="closeModal('addUserModal')" class="text-gray-400 hover:text-gray-600"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" required autocomplete="username" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required autocomplete="new-password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ-นามสกุล</label>
                    <input type="text" name="fullname" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                </div>
                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">ลายเซ็น (PNG/JPG)</label>
                                                    
                                                    <!-- Signature Input Type Selection -->
                                                    <div class="flex items-center gap-4 mb-3">
                                                        <label class="inline-flex items-center">
                                                            <input type="radio" name="sign_input_type_add" value="upload" checked class="form-radio text-brand-600 focus:ring-brand-500" onchange="toggleSignInput('add', 0)">
                                                            <span class="ml-2 text-sm text-gray-700">อัปโหลดไฟล์</span>
                                                        </label>
                                                        <label class="inline-flex items-center">
                                                            <input type="radio" name="sign_input_type_add" value="draw" class="form-radio text-brand-600 focus:ring-brand-500" onchange="toggleSignInput('add', 0)">
                                                            <span class="ml-2 text-sm text-gray-700">วาดลายเซ็นสด</span>
                                                        </label>
                                                    </div>

                                                    <!-- Upload Container -->
                                                    <div id="upload_container_add_0">
                                                        <input type="file" name="sign" accept="image/png, image/jpeg" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                                                    </div>

                                                    <!-- Draw Container -->
                                                    <div id="draw_container_add_0" class="hidden">
                                                        <div class="border-2 border-dashed border-gray-300 rounded-xl bg-gray-50/50 p-3 text-center">
                                                            <div id="preview_sign_add_0" class="hidden mb-3">
                                                                <img src="" class="h-16 mx-auto border border-gray-200 bg-white rounded-lg shadow-sm">
                                                                <span class="text-xs text-green-600 mt-2 block"><i class="bi bi-check-circle"></i> ลายเซ็นใหม่พร้อมบันทึก</span>
                                                            </div>
                                                            <button type="button" onclick="openAdminDrawModal(0)" class="bg-white border text-sm border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center gap-2 transition-colors">
                                                                <i class="bi bi-pen-fill text-brand-500"></i> เปิดกระดานวาดลายเซ็น
                                                            </button>
                                                            <input type="hidden" name="custom_sign_base64" id="custom_sign_base64_add_0" value="">
                                                        </div>
                                                    </div>
                                                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ตำแหน่ง</label>
                        <select name="position_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none text-sm">
                            <?php foreach ($positions as $id => $name): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">สิทธิ์</label>
                        <select name="role_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none text-sm">
                            <?php foreach ($roles as $id => $name): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ฝ่ายงาน</label>
                        <select name="dep_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none text-sm">
                            <?php foreach ($departments as $id => $name): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                <button type="button" onclick="closeModal('addUserModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">ยกเลิก</button>
                <button type="submit" name="add" class="px-4 py-2 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition-colors shadow-sm">เพิ่มข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<!-- Admin Draw Signature Modal -->
<div id="adminDrawModal" class="modal-overlay hidden fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 class="text-lg font-bold text-gray-800 flex items-center">
                <i class="bi bi-pen-fill text-brand-500 mr-2"></i> วาดลายเซ็น
            </h3>
            <button onclick="closeAdminDrawModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <div class="p-6 bg-gray-50">
            <div class="bg-white border-2 border-dashed border-gray-300 rounded-xl overflow-hidden relative" style="height: 250px;">
                <canvas id="admin-signature-pad" class="absolute inset-0 w-full h-full touch-none cursor-crosshair"></canvas>
            </div>
            <p class="text-center text-sm text-gray-500 mt-3">ใช้นิ้ว ปากกา หรือเมาส์ วาดลายเซ็นในกรอบด้านบน</p>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center bg-white">
            <button type="button" onclick="clearAdminDrawPad()" class="text-red-600 hover:text-red-700 text-sm font-medium flex items-center transition-colors">
                <i class="bi bi-eraser-fill mr-1"></i> ล้างกระดาน
            </button>
            <div class="flex gap-3">
                <button type="button" onclick="closeAdminDrawModal()" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">ยกเลิก</button>
                <button type="button" onclick="saveAdminDrawPad()" class="px-5 py-2.5 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-all shadow-sm flex items-center">
                    <i class="bi bi-check-lg mr-2"></i> ตกลง
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
    function toggleSignInput(mode, userId) {
        const type = document.querySelector(`input[name="sign_input_type_${mode === 'add' ? 'add' : userId}"]:checked`).value;
        const uploadCont = document.getElementById(`upload_container_${mode}_${userId}`);
        const drawCont = document.getElementById(`draw_container_${mode}_${userId}`);
        
        if (type === 'upload') {
            uploadCont.classList.remove('hidden');
            drawCont.classList.add('hidden');
        } else {
            uploadCont.classList.add('hidden');
            drawCont.classList.remove('hidden');
        }
    }

    let adminSignaturePad;
    let currentDrawingUserId = null;
    let currentDrawingMode = null;

    document.addEventListener("DOMContentLoaded", function() {
        const canvas = document.getElementById('admin-signature-pad');
        adminSignaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 0)',
            penColor: 'rgb(0, 0, 0)',
            minWidth: 1.5,
            maxWidth: 3
        });
    });

    function openAdminDrawModal(userId) {
        currentDrawingUserId = userId;
        currentDrawingMode = userId === 0 ? 'add' : 'edit';
        
        document.getElementById('adminDrawModal').classList.remove('hidden');
        
        setTimeout(() => {
            const canvas = document.getElementById('admin-signature-pad');
            const ratio =  Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            adminSignaturePad.clear();
        }, 200);
    }

    function closeAdminDrawModal() {
        document.getElementById('adminDrawModal').classList.add('hidden');
    }

    function clearAdminDrawPad() {
        adminSignaturePad.clear();
    }

    function saveAdminDrawPad() {
        if (adminSignaturePad.isEmpty()) {
            alert("กรุณาวาดลายเซ็น");
            return;
        }
        
        const dataURL = adminSignaturePad.toDataURL('image/png');
        const hiddenInputId = `custom_sign_base64_${currentDrawingMode}_${currentDrawingUserId}`;
        const previewContId = `preview_sign_${currentDrawingMode}_${currentDrawingUserId}`;
        
        document.getElementById(hiddenInputId).value = dataURL;
        const previewCont = document.getElementById(previewContId);
        previewCont.querySelector('img').src = dataURL;
        previewCont.classList.remove('hidden');
        
        closeAdminDrawModal();
    }
</script>

<?php
$content = ob_get_clean();
include 'base.php';
?>