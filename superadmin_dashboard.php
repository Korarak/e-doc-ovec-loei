<?php
ob_start();
session_start();
require_once 'auth_check.php';
require_superadmin();
include 'edoc-db.php';

// Fetch global stats
$instObj = $conn->query("SELECT COUNT(*) FROM institution WHERE inst_id != 999");
$totalInstitutions = $instObj ? $instObj->fetch_row()[0] : 0;

$userObj = $conn->query("SELECT COUNT(*) FROM user WHERE role_id != 99");
$totalUsers = $userObj ? $userObj->fetch_row()[0] : 0;

$docObj = $conn->query("SELECT COUNT(*) FROM documents");
$totalDocs = $docObj ? $docObj->fetch_row()[0] : 0;

// Fetch active institutions (recent uploads)
$recentQuery = "
    SELECT i.inst_name, COUNT(d.doc_id) as doc_count 
    FROM institution i 
    LEFT JOIN documents d ON i.inst_id = d.inst_id 
    WHERE i.inst_id != 999 
    GROUP BY i.inst_id 
    ORDER BY doc_count DESC 
    LIMIT 5
";
$activeInstitutions = $conn->query($recentQuery);
?>


<!-- ── Page Content ── -->
<div class="py-2">
    <!-- Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">System Overview</h1>
            <p class="text-slate-500 text-sm mt-1">ภาพรวมการใช้งานระบบ E-Sign ทั้งหมด</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="px-4 py-2 bg-white rounded-lg border border-slate-200 shadow-sm flex items-center gap-2">
                <i class="bi bi-clock text-brand-500"></i>
                <span class="text-sm font-medium text-slate-600"><?php echo date('d M Y, H:i'); ?></span>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Stat Card 1 -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/60 relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-brand-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-slate-500 mb-1">สถาบันทั้งหมด</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo number_format($totalInstitutions); ?></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center text-brand-600">
                    <i class="bi bi-building text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Stat Card 2 -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/60 relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-slate-500 mb-1">ผู้ใช้งานระบบ</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo number_format($totalUsers); ?></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600">
                    <i class="bi bi-people text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Stat Card 3 -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/60 relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-50 rounded-full group-hover:scale-110 transition-transform duration-500"></div>
            <div class="relative flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-slate-500 mb-1">เอกสารในระบบ</p>
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo number_format($totalDocs); ?></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600">
                    <i class="bi bi-file-earmark-text text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Institutions -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100">
            <h3 class="text-lg font-bold text-slate-800">Top Active Institutions</h3>
            <p class="text-sm text-slate-500">สถาบันที่มีเอกสารในระบบมากที่สุด 5 อันดับแรก</p>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php if ($activeInstitutions && $activeInstitutions->num_rows > 0): ?>
                    <?php while ($inst = $activeInstitutions->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg bg-white shadow-sm border border-slate-200 flex items-center justify-center text-slate-600">
                                    <i class="bi bi-building"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-slate-800"><?php echo htmlspecialchars($inst['inst_name']); ?></h4>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-slate-800"><?php echo number_format($inst['doc_count']); ?> เอกสาร</div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-500">
                        <i class="bi bi-inbox text-4xl mb-3 block text-slate-300"></i>
                        ไม่มีข้อมูลสถาบัน
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-6 text-center">
                <a href="superadmin_institutions.php" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700 bg-brand-50 hover:bg-brand-100 rounded-lg transition-colors">
                    จัดการสถาบันทั้งหมด <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean();
include 'base.php';
?>
