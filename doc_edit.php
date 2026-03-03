<?php
ob_start();
require_once 'auth_check.php';
require_role([1, 2]);
include 'edoc-db.php';

function uploadDocument($file, $doc_id) {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if (mime_content_type($file['tmp_name']) !== 'application/pdf') return false;
    $uploadDir = 'uploads/documents/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
    $safeFileName = uniqid('doc_', true) . '.pdf';
    $targetFilePath = $uploadDir . $safeFileName;
    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        global $conn;
        $stmt = $conn->prepare("INSERT INTO document_files (doc_id, file_path) VALUES (?, ?)");
        $stmt->bind_param("is", $doc_id, $targetFilePath);
        $stmt->execute();
        $stmt->close();
        return $targetFilePath;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_doc'])) {
    $doc_id = intval($_POST['doc_id']);
    $doc_from = trim($_POST['doc_from']);
    $doc_name = trim($_POST['doc_name']);
    $doc_type_id = intval($_POST['doc_type']);
    $sql = "UPDATE documents SET doc_name=?, doc_type_id=?, doc_from=? WHERE doc_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisi", $doc_name, $doc_type_id, $doc_from, $doc_id);
    if ($stmt->execute()) {
        $stmt->close();
        if (isset($_FILES['doc_files']) && $_FILES['doc_files']['name'][0] != '') {
            foreach ($_FILES['doc_files']['name'] as $key => $fileName) {
                $fileArray = [
                    'name' => $_FILES['doc_files']['name'][$key],
                    'tmp_name' => $_FILES['doc_files']['tmp_name'][$key],
                    'error' => $_FILES['doc_files']['error'][$key],
                    'size' => $_FILES['doc_files']['size'][$key],
                ];
                uploadDocument($fileArray, $doc_id);
            }
        }
        header("Location: doc_manage.php?msg=success");
        exit();
    }
}

if (isset($_GET['id'])) {
    $doc_id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM documents WHERE doc_id=?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$document) { header("Location: doc_manage.php"); exit(); }
} else {
    header("Location: doc_manage.php"); exit();
}
?>

<div class="py-2">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-amber-500 px-6 py-4">
                <h4 class="text-white font-bold text-lg flex items-center gap-2">
                    <i class="bi bi-pencil-square"></i> แก้ไขหนังสือเลขที่: <?php echo htmlspecialchars($document['doc_no']); ?>
                </h4>
            </div>
            <div class="p-6">
                <form id="docForm" method="POST" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="doc_id" value="<?= $document['doc_id'] ?>">
                    <input type="hidden" name="edit_doc" value="1">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">หน่วยงานต้นทาง <span class="text-red-500">*</span></label>
                            <input type="text" name="doc_from" value="<?= htmlspecialchars($document['doc_from']) ?>" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ประเภทหนังสือ <span class="text-red-500">*</span></label>
                            <select name="doc_type" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                                <?php
                                $dt_stmt = $conn->prepare("SELECT doc_type_id, doc_type_name FROM document_types WHERE inst_id = ? ORDER BY doc_type_id");
                                $dt_stmt->bind_param("i", $_SESSION['inst_id']);
                                $dt_stmt->execute();
                                $docTypes = $dt_stmt->get_result();
                                while ($docType = $docTypes->fetch_assoc()) {
                                    $selected = ($document['doc_type_id'] == $docType['doc_type_id']) ? 'selected' : '';
                                    echo "<option value=\"{$docType['doc_type_id']}\" $selected>" . htmlspecialchars($docType['doc_type_name']) . "</option>";
                                }
                                $dt_stmt->close();
                                ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">เรื่อง (ชื่อเอกสาร) <span class="text-red-500">*</span></label>
                        <input type="text" name="doc_name" value="<?= htmlspecialchars($document['doc_name']) ?>" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ไฟล์ปัจจุบัน</label>
                        <div class="space-y-2">
                        <?php
                        $fStmt = $conn->prepare("SELECT file_id, file_path FROM document_files WHERE doc_id = ?");
                        $fStmt->bind_param("i", $doc_id);
                        $fStmt->execute();
                        $fileResult = $fStmt->get_result();
                        if ($fileResult->num_rows > 0) {
                            while ($fileRow = $fileResult->fetch_assoc()) {
                                echo "<div class='flex items-center justify-between bg-gray-50 rounded-lg px-4 py-2.5 border border-gray-200'>
                                        <a href='" . htmlspecialchars($fileRow['file_path']) . "' target='_blank' class='inline-flex items-center gap-2 text-sm text-brand-700 hover:text-brand-800'>
                                            <i class='bi bi-file-earmark-pdf text-red-500'></i> ดูเอกสาร
                                        </a>
                                        <button type='button' onclick=\"confirmDeleteFile('doc_delete_file.php?file_id={$fileRow['file_id']}&doc_id={$doc_id}')\" class='inline-flex items-center gap-1 px-3 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 hover:bg-red-100 rounded-lg transition-colors'>
                                            <i class='bi bi-trash'></i> ลบ
                                        </button>
                                      </div>";
                            }
                        } else {
                            echo "<p class='text-sm text-gray-400'>ไม่มีไฟล์</p>";
                        }
                        ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">อัปโหลดไฟล์เพิ่มเติม (เฉพาะ PDF)</label>
                        <input type="file" name="doc_files[]" multiple accept="application/pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                    </div>

                    <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                        <a href="doc_manage.php" class="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="bi bi-arrow-left"></i> กลับ
                        </a>
                        <button type="button" onclick="confirmEdit()" class="inline-flex items-center gap-1 px-5 py-2.5 text-sm font-medium text-white bg-amber-500 hover:bg-amber-600 rounded-lg transition-colors shadow-sm">
                            <i class="bi bi-save"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmEdit() {
    const form = document.getElementById('docForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    Swal.fire({
        title: 'ยืนยันการแก้ไขข้อมูล', text: "คุณต้องการบันทึกการเปลี่ยนแปลงนี้หรือไม่?", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#f59e0b', cancelButtonColor: '#6b7280',
        confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก'
    }).then((r) => { if (r.isConfirmed) form.submit(); });
}
function confirmDeleteFile(url) {
    Swal.fire({
        title: 'ลบไฟล์?', text: "คุณไม่สามารถกู้คืนไฟล์นี้ได้!", icon: 'error',
        showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#6b7280',
        confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก'
    }).then((r) => { if (r.isConfirmed) window.location.href = url; });
}
</script>

<?php
$content = ob_get_clean();
include 'base.php';
?>
