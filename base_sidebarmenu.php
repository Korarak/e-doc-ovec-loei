<?php
$curr = basename($_SERVER['PHP_SELF']);
$active_class = "flex items-center px-4 py-2.5 bg-brand-500/10 text-brand-400 font-medium rounded-lg transition-colors group";
$inactive_class = "flex items-center px-4 py-2.5 text-slate-300 hover:bg-sidebarHover hover:text-white rounded-lg transition-colors group";

if (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 99): 
?>
<!-- ── Super Admin Menu ── -->
<div class="mb-4 pb-20">
    <div class="px-4 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">
        System Management
    </div>
    <nav class="space-y-1">
        <a href="superadmin_dashboard.php" class="<?= $curr == 'superadmin_dashboard.php' ? $active_class : $inactive_class ?>">
            <i class="bi bi-house-door text-lg mr-3 <?= $curr == 'superadmin_dashboard.php' ? 'text-brand-400' : 'text-brand-500 group-hover:text-brand-400' ?>"></i>
            <span>System Overview</span>
        </a>
        <a href="superadmin_institutions.php" class="<?= $curr == 'superadmin_institutions.php' ? $active_class : $inactive_class ?>">
            <i class="bi bi-building text-lg mr-3 <?= $curr == 'superadmin_institutions.php' ? 'text-brand-400' : 'text-brand-500 group-hover:text-brand-400' ?>"></i>
            <span>จัดการสถาบัน (วิทยาลัย)</span>
        </a>
    </nav>
</div>

<?php else: ?>
<!-- ── Standard Application Menu ── -->
<div class="mb-4">
    <div class="px-4 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">
        ผู้ดูแลระบบ
    </div>
    <nav class="space-y-1">
        <a href="dashboard.php" class="<?= $curr == 'dashboard.php' ? $active_class : $inactive_class ?>">
            <i class="bi bi-house-door text-lg mr-3 <?= $curr == 'dashboard.php' ? 'text-brand-400' : 'text-brand-500 group-hover:text-brand-400' ?>"></i>
            <span>Dashboard</span>
        </a>
        <a href="user_manage.php" class="<?= $curr == 'user_manage.php' ? $active_class : $inactive_class ?>">
            <i class="bi bi-people text-lg mr-3 <?= $curr == 'user_manage.php' ? 'text-brand-400' : 'text-brand-500 group-hover:text-brand-400' ?>"></i>
            <span>จัดการข้อมูลผู้ใช้</span>
        </a>
        <a href="doc_manage.php" class="<?= in_array($curr, ['doc_manage.php', 'doc_add.php', 'doc_edit.php']) ? $active_class : $inactive_class ?>">
            <i class="bi bi-file-earmark-text text-lg mr-3 <?= in_array($curr, ['doc_manage.php', 'doc_add.php', 'doc_edit.php']) ? 'text-brand-400' : 'text-brand-500 group-hover:text-brand-400' ?>"></i>
            <span>จัดการหนังสือราชการ</span>
        </a>
        <a href="user_signature.php" class="<?= $curr == 'user_signature.php' ? $active_class : $inactive_class ?>">
            <i class="bi bi-pen-fill text-lg mr-3 <?= $curr == 'user_signature.php' ? 'text-brand-400' : 'text-brand-500 group-hover:text-brand-400' ?>"></i>
            <span>ลายเซ็นส่วนตัว</span>
        </a>
    </nav>
</div>

<?php
// Ensure database connection is available for role checking
require_once 'edoc-db.php';
require_once 'auth_check.php';
$is_sarabun = is_sarabun($conn, $_SESSION['user_id']);
$is_codirector = is_codirector($conn, $_SESSION['user_id']);
$is_director = is_director($conn, $_SESSION['user_id']);
?>

<div class="mb-4">
    <div class="px-4 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">
        งานสารบรรณและการลงนาม
    </div>
    <nav class="space-y-1">
        <?php if ($is_sarabun || (int)$_SESSION['role_id'] === 1): ?>
        <a href="sarabun_manage.php" class="<?= in_array($curr, ['sarabun_manage.php', 'sarabun_sign.php', 'sarabun_signtxt.php']) ? $active_class : $inactive_class ?>">
            <i class="bi bi-inbox text-lg mr-3 <?= in_array($curr, ['sarabun_manage.php', 'sarabun_sign.php', 'sarabun_signtxt.php']) ? 'text-amber-300' : 'text-amber-400 group-hover:text-amber-300' ?>"></i>
            <span>งานสารบรรณ</span>
        </a>
        <?php endif; ?>
        
        <?php if ($is_codirector || (int)$_SESSION['role_id'] === 1): ?>
        <a href="codirector_manage.php" class="<?= in_array($curr, ['codirector_manage.php', 'codirector_sign.php']) ? $active_class : $inactive_class ?>">
            <i class="bi bi-person-fill-check text-lg mr-3 <?= in_array($curr, ['codirector_manage.php', 'codirector_sign.php']) ? 'text-sky-300' : 'text-sky-400 group-hover:text-sky-300' ?>"></i>
            <span>รองผู้อำนวยการ</span>
        </a>
        <?php endif; ?>
        
        <?php if ($is_director || (int)$_SESSION['role_id'] === 1): ?>
        <a href="director_manage.php" class="<?= in_array($curr, ['director_manage.php', 'director_sign.php']) ? $active_class : $inactive_class ?>">
            <i class="bi bi-award text-lg mr-3 <?= in_array($curr, ['director_manage.php', 'director_sign.php']) ? 'text-rose-300' : 'text-rose-400 group-hover:text-rose-300' ?>"></i>
            <span>ผู้อำนวยการ</span>
        </a>
        <?php endif; ?>
    </nav>
</div>

<div class="mb-4 pb-20">
    <div class="px-4 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">
        ตั้งค่า
    </div>
    <nav class="space-y-1">
        <a href="setting_doc_type.php" class="<?= $curr == 'setting_doc_type.php' ? $active_class : $inactive_class ?>">
            <i class="bi bi-file-earmark-ruled text-lg mr-3 <?= $curr == 'setting_doc_type.php' ? 'text-slate-300' : 'text-slate-400 group-hover:text-slate-300' ?>"></i>
            <span>ตั้งค่าประเภทหนังสือ</span>
        </a>
        <a href="setting_position.php" class="<?= $curr == 'setting_position.php' ? $active_class : $inactive_class ?>">
            <i class="bi bi-person-badge text-lg mr-3 <?= $curr == 'setting_position.php' ? 'text-slate-300' : 'text-slate-400 group-hover:text-slate-300' ?>"></i>
            <span>ตั้งค่าตำแหน่ง</span>
        </a>
        <a href="setting_department.php" class="<?= $curr == 'setting_department.php' ? $active_class : $inactive_class ?>">
            <i class="bi bi-building text-lg mr-3 <?= $curr == 'setting_department.php' ? 'text-slate-300' : 'text-slate-400 group-hover:text-slate-300' ?>"></i>
            <span>ตั้งค่าฝ่ายงาน</span>
        </a>
    </nav>
</div>
<?php endif; ?>