<?php
ob_start();
require_once 'auth_check.php';
check_csrf();
require_role([1, 2]);
include 'edoc-db.php';

function generateDocNo($conn, $docTypeId) {
    $currentYear = date('Y') + 543;
    $inst_id = $_SESSION['inst_id'];
    $sql = "SELECT MAX(doc_no) AS max_no FROM documents WHERE doc_type_id = ? AND inst_id = ? AND doc_no LIKE '%/$currentYear'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $docTypeId, $inst_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $sequenceNumber = $row['max_no'] ? (int)explode('/', $row['max_no'])[0] + 1 : 1;
    return str_pad($sequenceNumber, 2, '0', STR_PAD_LEFT) . '/' . $currentYear;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $inst_id = $_SESSION['inst_id'];
    $doc_from = trim($_POST['doc_from']);
    $doc_name = trim($_POST['doc_name']);
    $doc_type_id = intval($_POST['doc_type_id']);
    $doc_uploader = intval($_POST['doc_uploader']);
    $doc_upload_date = date('Y-m-d H:i:s');
    $doc_no = generateDocNo($conn, $doc_type_id);

    $sql = "INSERT INTO documents (inst_id, doc_no, doc_name, doc_upload_date, doc_type_id, doc_uploader, doc_from) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssiis", $inst_id, $doc_no, $doc_name, $doc_upload_date, $doc_type_id, $doc_uploader, $doc_from);
    if ($stmt->execute()) {
        $doc_id = $stmt->insert_id;
        $stmt->close();
        if (isset($_FILES['doc_files'])) {
            $uploadDir = 'uploads/documents/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            foreach ($_FILES['doc_files']['name'] as $index => $fileName) {
                if ($_FILES['doc_files']['error'][$index] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['doc_files']['tmp_name'][$index];
                    if (mime_content_type($tmpName) === 'application/pdf') {
                        $safeFileName = uniqid('doc_', true) . '.pdf';
                        $targetFilePath = $uploadDir . $safeFileName;
                        if (move_uploaded_file($tmpName, $targetFilePath)) {
                            $fStmt = $conn->prepare("INSERT INTO document_files (doc_id, file_path) VALUES (?, ?)");
                            $fStmt->bind_param("is", $doc_id, $targetFilePath);
                            $fStmt->execute();
                            $fStmt->close();
                        }
                    }
                }
            }
        }
        header("Location: doc_manage.php?msg=success");
        exit();
    }
}
?>

<div class="py-2">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-brand-600 px-6 py-4">
                <h4 class="text-white font-bold text-lg flex items-center gap-2">
                    <i class="bi bi-file-earmark-plus"></i> เพิ่มหนังสือใหม่
                </h4>
            </div>
            <div class="p-6">
                <form id="docForm" method="POST" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">หน่วยงานต้นทาง <span class="text-red-500">*</span></label>
                            <input type="text" name="doc_from" placeholder="เช่น วิทยาลัยเทคนิคเลย" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ประเภทหนังสือ <span class="text-red-500">*</span></label>
                            <select name="doc_type_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                                <option value="" disabled selected>-- เลือกประเภท --</option>
                                <?php
                                $dt_stmt = $conn->prepare("SELECT doc_type_id, doc_type_name FROM document_types WHERE inst_id = ? ORDER BY doc_type_id");
                                $dt_stmt->bind_param("i", $_SESSION['inst_id']);
                                $dt_stmt->execute();
                                $result = $dt_stmt->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . $row['doc_type_id'] . "'>" . htmlspecialchars($row['doc_type_name']) . "</option>";
                                }
                                $dt_stmt->close();
                                ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">เรื่อง (ชื่อเอกสาร) <span class="text-red-500">*</span></label>
                        <input type="text" name="doc_name" placeholder="ชื่อหนังสือราชการ" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ไฟล์หนังสือราชการ (เฉพาะ PDF) <span class="text-red-500">*</span></label>
                        <input type="file" name="doc_files[]" multiple accept="application/pdf" required class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                        <p class="text-xs text-gray-400 mt-1">สามารถเลือกได้หลายไฟล์ เฉพาะ .pdf เท่านั้น</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ผู้อัปโหลด</label>
                        <input type="text" value="<?php echo htmlspecialchars($_SESSION['fullname']); ?>" disabled class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-gray-500">
                        <input type="hidden" name="doc_uploader" value="<?php echo $_SESSION['user_id']; ?>">
                    </div>

                    <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                        <a href="doc_manage.php" class="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="bi bi-arrow-left"></i> กลับ
                        </a>
                        <button type="button" onclick="confirmSubmit()" class="inline-flex items-center gap-1 px-5 py-2.5 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition-colors shadow-sm">
                            <i class="bi bi-save"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmSubmit() {
    const form = document.getElementById('docForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    Swal.fire({
        title: 'ยืนยันการเพิ่มเอกสาร', text: "คุณตรวจสอบข้อมูลครบถ้วนแล้วใช่หรือไม่?", icon: 'question',
        showCancelButton: true, confirmButtonColor: '#16a34a', cancelButtonColor: '#6b7280',
        confirmButtonText: 'ยืนยันบันทึก', cancelButtonText: 'ยกเลิก'
    }).then((r) => { if (r.isConfirmed) form.submit(); });
}
</script>

<?php
$content = ob_get_clean();
include 'base.php';
?>
