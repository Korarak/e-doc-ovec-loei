<?php
ob_start();
session_start();
require_once 'auth_check.php';
require_superadmin();
include 'edoc-db.php';

$success = '';
$error = '';

// --- Handle Add Institution ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_inst') {
    $inst_name = trim($_POST['inst_name']);
    $admin_username = trim($_POST['admin_username']);
    $admin_password = $_POST['admin_password'];

    if (empty($inst_name) || empty($admin_username) || empty($admin_password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        // Check if username exists globally
        $chk_user = $conn->prepare("SELECT user_id FROM user WHERE username = ?");
        $chk_user->bind_param("s", $admin_username);
        $chk_user->execute();
        if ($chk_user->get_result()->num_rows > 0) {
            $error = "ชื่อบัญชีผู้ใช้นี้ (Username) ถูกใช้งานแล้ว กรุณาใช้ชื่ออื่น";
        } else {
            $conn->begin_transaction();
            try {
                // 1. Insert Institution
                $stmt = $conn->prepare("INSERT INTO institution (inst_name) VALUES (?)");
                $stmt->bind_param("s", $inst_name);
                $stmt->execute();
                $new_inst_id = $conn->insert_id;

                // 2. Insert Default Departments
                $conn->query("INSERT INTO department (inst_id, dep_name) VALUES 
                    ($new_inst_id, 'ผู้บริหาร'),
                    ($new_inst_id, 'งานสารบรรณ (กลาง)')
                ");
                // Get the sarabun dep_id for the admin user
                $res_dep = $conn->query("SELECT dep_id FROM department WHERE inst_id = $new_inst_id AND dep_name LIKE '%สารบรรณ%' LIMIT 1");
                $admin_dep_id = $res_dep->fetch_assoc()['dep_id'] ?? null;

                // 3. Insert Default Document Types
                $conn->query("INSERT INTO document_types (inst_id, doc_type_name) VALUES 
                    ($new_inst_id, 'หนังสือภายนอก'),
                    ($new_inst_id, 'หนังสือภายใน'),
                    ($new_inst_id, 'หนังสือสั่งการ'),
                    ($new_inst_id, 'หนังสือประชาสัมพันธ์'),
                    ($new_inst_id, 'หนังสือที่เจ้าหน้าที่ทำขึ้น')
                ");

                // 4. Insert Default Positions
                $conn->query("INSERT INTO position (inst_id, position_name) VALUES 
                    ($new_inst_id, 'ผู้อำนวยการ'),
                    ($new_inst_id, 'รองผู้อำนวยการ'),
                    ($new_inst_id, 'ครู'),
                    ($new_inst_id, 'เจ้าหน้าที่ธุรการ'),
                    ($new_inst_id, 'พนักงานราชการ')
                ");

                // 5. Create Admin Account (Role 1)
                $hashed_pw = password_hash($admin_password, PASSWORD_BCRYPT);
                $stmt_user = $conn->prepare("INSERT INTO user (inst_id, username, password, fullname, role_id, dep_id) VALUES (?, ?, ?, 'ผู้ดูแลระบบ (Admin)', 1, ?)");
                $stmt_user->bind_param("issi", $new_inst_id, $admin_username, $hashed_pw, $admin_dep_id);
                $stmt_user->execute();

                $conn->commit();
                $success = "เพิ่มสถาบัน {$inst_name} และบัญชีแอดมิน {$admin_username} สำเร็จ!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "เกิดข้อผิดพลาดในการสร้างสถาบัน: " . $e->getMessage();
            }
        }
    }
}

// --- Handle Delete Institution ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'del_inst') {
    $del_id = (int)$_POST['inst_id'];
    if ($del_id != 999) {
        $stmt = $conn->prepare("DELETE FROM institution WHERE inst_id = ?");
        $stmt->bind_param("i", $del_id);
        if ($stmt->execute()) {
            $success = "ลบสถาบันและข้อมูลที่เกี่ยวข้องทั้งหมดสำเร็จแล้ว";
        } else {
            $error = "ลบสถาบันไม่สำเร็จ";
        }
    }
}

// Fetch Institutions
$query = "
    SELECT i.inst_id, i.inst_name, 
           (SELECT COUNT(*) FROM user WHERE inst_id = i.inst_id) as user_count,
           (SELECT COUNT(*) FROM documents WHERE inst_id = i.inst_id) as doc_count,
           (SELECT username FROM user WHERE inst_id = i.inst_id AND role_id = 1 LIMIT 1) as admin_username
    FROM institution i
    WHERE i.inst_id != 999
    ORDER BY i.inst_id DESC
";
$institutions = $conn->query($query);
?>


<div class="py-2">
    <!-- Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">จัดการสถาบัน (Institution Management)</h1>
            <p class="text-slate-500 text-sm mt-1">เพิ่ม/ลบ สถาบันที่ใช้งานระบบ E-Sign</p>
        </div>
        <button onclick="document.getElementById('modalAdd').classList.remove('hidden')" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg shadow-sm font-medium transition-colors flex items-center gap-2">
            <i class="bi bi-plus-lg"></i> เพิ่มสถาบันใหม่
        </button>
    </div>

    <?php if($success): ?>
        <div class="mb-6 bg-brand-50 border border-brand-200 text-brand-700 px-4 py-3 rounded-lg flex items-center gap-3">
            <i class="bi bi-check-circle-fill text-lg"></i>
            <div><?php echo htmlspecialchars($success); ?></div>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-3">
            <i class="bi bi-exclamation-triangle-fill text-lg"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-sm text-slate-500 font-medium">
                        <th class="py-4 px-6">ID</th>
                        <th class="py-4 px-6">ชื่อสถาบัน</th>
                        <th class="py-4 px-6">แอดมินประจำสถาบัน</th>
                        <th class="py-4 px-6 text-center">ผู้ใช้งาน</th>
                        <th class="py-4 px-6 text-center">เอกสาร</th>
                        <th class="py-4 px-6 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/80">
                    <?php if ($institutions && $institutions->num_rows > 0): ?>
                        <?php while ($row = $institutions->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-6 text-slate-500">#<?php echo $row['inst_id']; ?></td>
                                <td class="py-4 px-6 font-medium text-slate-800"><?php echo htmlspecialchars($row['inst_name']); ?></td>
                                <td class="py-4 px-6 text-slate-600">
                                    <?php if ($row['admin_username']): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-sky-50 text-sky-700 text-xs font-medium border border-sky-100">
                                            <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($row['admin_username']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-sm">ไม่มี Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-center text-slate-600"><?php echo number_format($row['user_count']); ?></td>
                                <td class="py-4 px-6 text-center text-slate-600"><?php echo number_format($row['doc_count']); ?></td>
                                <td class="py-4 px-6 text-right text-sm">
                                    <form method="POST" class="inline-block" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบสถาบันนี้? (ข้อมูลทุกอย่างรวมถึงเอกสารและผู้ใช้จะถูกลบทั้งหมด!)');">
                                        <input type="hidden" name="action" value="del_inst">
                                        <input type="hidden" name="inst_id" value="<?php echo $row['inst_id']; ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg transition-colors font-medium">
                                            <i class="bi bi-trash"></i> ลบ
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-500">
                                <div class="bg-slate-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="bi bi-building text-2xl text-slate-300"></i>
                                </div>
                                ไม่มีข้อมูลสถาบันระบบ
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add Institution -->
<div id="modalAdd" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden transform transition-all">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="text-lg font-bold text-slate-800">เพิ่มสถาบันใหม่</h3>
            <button type="button" onclick="document.getElementById('modalAdd').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 p-1">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="mb-4 bg-blue-50 border border-blue-100 rounded-lg p-3 text-sm text-blue-700">
                <i class="bi bi-info-circle-fill mr-1"></i> เมื่อเพิ่มสถาบัน ระบบจะสร้าง ตำแหน่ง, ประเภทหนังสือพื้นฐาน และบัญชี Admin ให้โดยอัตโนมัติ
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_inst">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">ชื่อสถาบัน <span class="text-red-500">*</span></label>
                        <input type="text" name="inst_name" required placeholder="เช่น วิทยาลัยการอาชีพ..." class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-colors">
                    </div>
                    
                    <div class="pt-2 border-t border-slate-100">
                        <h4 class="text-sm font-semibold text-slate-800 mb-3">ข้อมูลบัญชีผู้ดูแลระบบ (Admin) ของสถาบัน</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Username <span class="text-red-500">*</span></label>
                                <input type="text" name="admin_username" required placeholder="เช่น admin_bkk" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-colors">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Password <span class="text-red-500">*</span></label>
                                <input type="password" name="admin_password" required placeholder="พาสเวิร์ดเริ่มต้น" autocomplete="new-password" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-colors">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modalAdd').classList.add('hidden')" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg font-medium transition-colors">
                        ยกเลิก
                    </button>
                    <button type="submit" class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg shadow-sm font-medium transition-colors flex items-center gap-2">
                        <i class="bi bi-save"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean();
include 'base.php';
?>
