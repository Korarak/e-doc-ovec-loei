<?php
ob_start();
session_start();
require_once 'auth_check.php';

include('edoc-db.php');

$user_id = $_SESSION['user_id'];

// ────────────────────────────────────────────
// ดึงข้อมูลผู้ใช้
// ────────────────────────────────────────────
$query = "
    SELECT 
        u.fullname, u.username,
        p.position_name,
        r.role_name,
        d.dep_name
    FROM user u
    LEFT JOIN position p ON u.position_id = p.position_id
    LEFT JOIN role r ON u.role_id = r.role_id
    LEFT JOIN department d ON u.dep_id = d.dep_id
    WHERE u.user_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($fullname, $username, $position_name, $role_name, $dep_name);
$stmt->fetch();
$stmt->close();

// ────────────────────────────────────────────
// ดึงสถิติ
// ────────────────────────────────────────────
$inst_id = $_SESSION['inst_id'];

$stmt = $conn->prepare("SELECT COUNT(*) FROM documents WHERE inst_id = ?");
$stmt->bind_param("i", $inst_id); $stmt->execute(); $totalDocs = $stmt->get_result()->fetch_row()[0]; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM sign_doc sd JOIN documents d ON sd.doc_id = d.doc_id WHERE sd.sign_sarabun = 'pending' AND d.inst_id = ?");
$stmt->bind_param("i", $inst_id); $stmt->execute(); $pendingDocs = $stmt->get_result()->fetch_row()[0]; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM sign_doc sd JOIN documents d ON sd.doc_id = d.doc_id WHERE sd.sign_sarabun='approve' AND sd.sign_codirector='approve' AND sd.sign_director='approve' AND d.inst_id = ?");
$stmt->bind_param("i", $inst_id); $stmt->execute(); $approvedAll = $stmt->get_result()->fetch_row()[0]; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM sign_doc sd JOIN documents d ON sd.doc_id = d.doc_id WHERE sd.sign_sarabun='approve' AND sd.sign_codirector='pending' AND d.inst_id = ?");
$stmt->bind_param("i", $inst_id); $stmt->execute(); $waitingCoDir = $stmt->get_result()->fetch_row()[0]; $stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM sign_doc sd JOIN documents d ON sd.doc_id = d.doc_id WHERE sd.sign_codirector='approve' AND sd.sign_director='pending' AND d.inst_id = ?");
$stmt->bind_param("i", $inst_id); $stmt->execute(); $waitingDir = $stmt->get_result()->fetch_row()[0]; $stmt->close();

// ────────────────────────────────────────────
// ดึง 5 หนังสือล่าสุด
// ────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT d.doc_no, d.doc_name, d.doc_from, dt.doc_type_name,
           d.doc_upload_date,
           COALESCE(sd.sign_sarabun,'–') AS sign_sarabun,
           COALESCE(sd.sign_codirector,'–') AS sign_codirector,
           COALESCE(sd.sign_director,'–') AS sign_director
    FROM documents d
    LEFT JOIN document_types dt ON d.doc_type_id = dt.doc_type_id
    LEFT JOIN sign_doc sd ON d.doc_id = sd.doc_id
    WHERE d.inst_id = ?
    ORDER BY d.doc_upload_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $inst_id);
$stmt->execute();
$recentDocs = $stmt->get_result();
?>

<div class="py-2">

  <!-- ── Welcome ── -->
  <div class="flex justify-between items-center mb-6 bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
    <div>
      <h4 class="text-xl font-bold text-gray-800 mb-1">👋 สวัสดี, <?php echo htmlspecialchars($fullname ?? ''); ?>!</h4>
      <span class="text-sm font-medium text-gray-500"><?php echo htmlspecialchars($position_name ?? ''); ?> &bull; <?php echo htmlspecialchars($dep_name ?? ''); ?></span>
    </div>
    <a href="logout.php" class="inline-flex items-center px-4 py-2 border-2 border-red-500 text-red-600 hover:bg-red-50 font-medium rounded-xl text-sm transition-colors" onclick="confirmLogout(event, 'logout.php')">
      <i class="bi bi-box-arrow-right mr-2"></i> ออกจากระบบ
    </a>
  </div>

  <!-- ── Stats Cards ── -->
  <div class="grid grid-cols-2 md:grid-cols-4 tablet-grid gap-4 mb-6">
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 text-center relative overflow-hidden group">
        <div class="absolute inset-x-0 bottom-0 h-1 bg-brand-500 transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left text-brand-500"></div>
        <div class="text-4xl font-extrabold text-slate-700 mb-2"><?php echo $totalDocs; ?></div>
        <div class="text-sm font-medium text-slate-500">หนังสือทั้งหมด</div>
    </div>
    
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 text-center relative overflow-hidden group">
        <div class="absolute inset-x-0 bottom-0 h-1 bg-amber-500 transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left text-amber-500"></div>
        <div class="text-4xl font-extrabold text-amber-500 mb-2"><?php echo $pendingDocs; ?></div>
        <div class="text-sm font-medium text-slate-500">รอสารบรรณรับ</div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 text-center relative overflow-hidden group">
        <div class="absolute inset-x-0 bottom-0 h-1 bg-sky-500 transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left text-sky-500"></div>
        <div class="text-4xl font-extrabold text-sky-500 mb-2"><?php echo $waitingCoDir + $waitingDir; ?></div>
        <div class="text-sm font-medium text-slate-500">รอลงนาม</div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 text-center relative overflow-hidden group">
        <div class="absolute inset-x-0 bottom-0 h-1 bg-emerald-500 transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left text-emerald-500"></div>
        <div class="text-4xl font-extrabold text-emerald-500 mb-2"><?php echo $approvedAll; ?></div>
        <div class="text-sm font-medium text-slate-500">เสร็จสิ้นแล้ว</div>
    </div>
  </div>

  <!-- ── Quick Links ── -->
  <div class="grid grid-cols-2 lg:grid-cols-4 tablet-grid gap-4 mb-8">
      <a href="doc_add.php" class="flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-3 rounded-xl font-medium transition-colors shadow-sm">
        <i class="bi bi-plus-circle text-lg"></i> เพิ่มหนังสือใหม่
      </a>
      <a href="sarabun_manage.php" class="flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-4 py-3 rounded-xl font-medium transition-colors shadow-sm relative">
        <i class="bi bi-inbox text-lg"></i> งานสารบรรณ
        <?php if ($pendingDocs > 0): ?>
          <span class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white shadow-sm ring-2 ring-white border-white"><?php echo $pendingDocs; ?></span>
        <?php endif; ?>
      </a>
      <a href="codirector_manage.php" class="flex items-center justify-center gap-2 bg-sky-500 hover:bg-sky-600 text-white px-4 py-3 rounded-xl font-medium transition-colors shadow-sm relative">
        <i class="bi bi-person-fill-check text-lg"></i> รองผู้อำนวยการ
        <?php if ($waitingCoDir > 0): ?>
          <span class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white shadow-sm ring-2 ring-white"><?php echo $waitingCoDir; ?></span>
        <?php endif; ?>
      </a>
      <a href="director_manage.php" class="flex items-center justify-center gap-2 bg-rose-500 hover:bg-rose-600 text-white px-4 py-3 rounded-xl font-medium transition-colors shadow-sm relative">
        <i class="bi bi-award text-lg"></i> ผู้อำนวยการ
        <?php if ($waitingDir > 0): ?>
          <span class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white shadow-sm ring-2 ring-white"><?php echo $waitingDir; ?></span>
        <?php endif; ?>
      </a>
  </div>

  <!-- ── Recent Documents ── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
      <h5 class="text-lg font-bold text-gray-800 flex items-center gap-2">
        <i class="bi bi-file-earmark-text text-brand-500"></i> หนังสือล่าสุด 5 รายการ
      </h5>
      <a href="doc_manage.php" class="text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline">ดูทั้งหมด &rarr;</a>
    </div>
    
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm text-gray-600">
        <thead class="bg-gray-50/50 text-gray-700 border-b border-gray-100 font-medium">
          <tr>
            <th class="px-6 py-3 whitespace-nowrap">เลขที่</th>
            <th class="px-6 py-3 whitespace-nowrap">ประเภท</th>
            <th class="px-6 py-3">เรื่อง</th>
            <th class="px-6 py-3">จาก</th>
            <th class="px-6 py-3 whitespace-nowrap">วันที่</th>
            <th class="px-6 py-3 text-center">สารบรรณ</th>
            <th class="px-6 py-3 text-center">รอง ผอ.</th>
            <th class="px-6 py-3 text-center">ผอ.</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php while ($row = $recentDocs->fetch_assoc()): ?>
          <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?php echo htmlspecialchars($row['doc_no']); ?></td>
            <td class="px-6 py-4 whitespace-nowrap"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800"><?php echo htmlspecialchars($row['doc_type_name']); ?></span></td>
            <td class="px-6 py-4 min-w-[200px]"><?php echo htmlspecialchars(mb_substr($row['doc_name'], 0, 40)) . (mb_strlen($row['doc_name']) > 40 ? '…' : ''); ?></td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-500"><?php echo htmlspecialchars($row['doc_from']); ?></td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-500"><?php echo formatThaiDate($row['doc_upload_date']); ?></td>
            <td class="px-6 py-4 text-center">
              <?php
              $s = $row['sign_sarabun'];
              echo $s === 'approve' ? '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600"><i class="bi bi-check-lg"></i></span>' : ($s === 'pending' ? '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-100 text-amber-700">รอ</span>' : '<span class="text-gray-300">–</span>');
              ?>
            </td>
            <td class="px-6 py-4 text-center">
              <?php
              $s = $row['sign_codirector'];
              echo $s === 'approve' ? '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600"><i class="bi bi-check-lg"></i></span>' : ($s === 'pending' ? '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-100 text-amber-700">รอ</span>' : '<span class="text-gray-300">–</span>');
              ?>
            </td>
            <td class="px-6 py-4 text-center">
              <?php
              $s = $row['sign_director'];
              echo $s === 'approve' ? '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600"><i class="bi bi-check-lg"></i></span>' : ($s === 'pending' ? '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-700">รอ</span>' : '<span class="text-gray-300">–</span>');
              ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php
$content = ob_get_clean();
include 'base.php';
?>
