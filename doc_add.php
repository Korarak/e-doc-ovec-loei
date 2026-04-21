<?php
ob_start();
require_once 'auth_check.php';
check_csrf();
require_role([1]);
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
                    $allowedMimes = ['application/pdf','image/jpeg','image/png','image/gif','image/webp','image/tiff'];
                    $fileMime = mime_content_type($tmpName);
                    if (in_array($fileMime, $allowedMimes)) {
                        $extMap = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/tiff'=>'tif'];
                        $safeFileName = uniqid('doc_', true) . '.' . ($extMap[$fileMime] ?? 'bin');
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

<?php
/* ดึง document_types มาไว้ใน PHP array เพื่อใช้ใน JS auto-select */
$docTypesForJs = [];
$dt_stmt2 = $conn->prepare("SELECT doc_type_id, doc_type_name FROM document_types WHERE inst_id = ? ORDER BY doc_type_id");
$dt_stmt2->bind_param("i", $_SESSION['inst_id']);
$dt_stmt2->execute();
$dt_result2 = $dt_stmt2->get_result();
while ($r2 = $dt_result2->fetch_assoc()) {
    $docTypesForJs[] = ['id' => $r2['doc_type_id'], 'name' => $r2['doc_type_name']];
}
$dt_stmt2->close();
?>

<!-- PDF.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>

<style>
@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
@keyframes pulse-border {
    0%, 100% { border-color: #7c3aed; box-shadow: 0 0 0 0 rgba(124,58,237,0.2); }
    50% { border-color: #6d28d9; box-shadow: 0 0 0 6px rgba(124,58,237,0); }
}
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-6px); }
}
@keyframes scan-line {
    0% { top: 0%; opacity: 1; }
    100% { top: 100%; opacity: 0.3; }
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes typing-cursor {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}
.ai-badge {
    background: linear-gradient(135deg, #7c3aed, #2563eb);
    background-size: 200% 200%;
    animation: shimmer 3s ease infinite;
}
.ai-drop-zone {
    border: 2px dashed #c4b5fd;
    background: linear-gradient(135deg, #faf5ff 0%, #eff6ff 100%);
    transition: all 0.3s ease;
    cursor: pointer;
}
.ai-drop-zone:hover, .ai-drop-zone.drag-over {
    border-color: #7c3aed;
    background: linear-gradient(135deg, #f3e8ff 0%, #dbeafe 100%);
    transform: scale(1.01);
    animation: pulse-border 1.5s ease infinite;
}
.ai-icon-float { animation: float 3s ease-in-out infinite; }
.scan-container { position: relative; overflow: hidden; }
.scan-line {
    position: absolute; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, transparent, #7c3aed, #2563eb, transparent);
    animation: scan-line 1.2s ease-in-out infinite;
    border-radius: 2px;
}
.field-highlight {
    border-color: #7c3aed !important;
    box-shadow: 0 0 0 3px rgba(124,58,237,0.1) !important;
    background: linear-gradient(135deg, #faf5ff, white) !important;
    animation: fadeInUp 0.4s ease;
}
.ai-result-badge {
    background: linear-gradient(135deg, #f3e8ff, #dbeafe);
    border: 1px solid #c4b5fd;
    animation: fadeInUp 0.3s ease;
}
.confidence-bar {
    height: 4px; border-radius: 2px;
    background: linear-gradient(90deg, #7c3aed, #2563eb);
    transition: width 1s ease;
}
.typing-cursor::after {
    content: '|';
    animation: typing-cursor 0.7s infinite;
    color: #7c3aed;
    font-weight: bold;
}
/* Candidate suggestion tags */
.ocr-candidate-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
    background: #f3e8ff;
    border: 1px solid #c4b5fd;
    color: #6d28d9;
    cursor: pointer;
    transition: all 0.15s ease;
    animation: fadeInUp 0.25s ease;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ocr-candidate-tag:hover {
    background: #7c3aed;
    border-color: #7c3aed;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(124,58,237,0.3);
}
.ocr-candidate-tag.active {
    background: #7c3aed;
    border-color: #7c3aed;
    color: #fff;
}
</style>

<div class="py-2">
    <div class="max-w-3xl mx-auto space-y-4">

        <!-- AI Scan Card -->
        <div class="rounded-xl overflow-hidden shadow-sm border border-purple-100">
            <div class="ai-badge px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2 text-white">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
                    </svg>
                    <span id="scanMethodTitle" class="font-bold text-sm tracking-wide">ระบบอ่านข้อมูล OCR — อ่านข้อมูลจาก PDF อัตโนมัติ</span>
                </div>
                <!-- Method Toggle -->
                <div class="flex items-center gap-1 bg-white/15 rounded-lg p-0.5">
                    <button id="methodOcr" type="button" onclick="setMethod('ocr')"
                            class="px-3 py-1 rounded-md text-xs font-semibold text-white bg-white/25 transition-all">
                        ⚡ OCR
                    </button>
                    <button id="methodGemini" type="button" onclick="setMethod('gemini')"
                            class="px-3 py-1 rounded-md text-xs font-semibold text-white/60 transition-all hover:text-white">
                        ✨ Gemini AI
                    </button>
                </div>
            </div>
            <div class="bg-white p-5">
                <!-- Drop Zone -->
                <div id="aiDropZone" class="ai-drop-zone rounded-xl p-8 text-center mb-4" onclick="document.getElementById('aiPdfInput').click()">
                    <input type="file" id="aiPdfInput" accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,image/tiff" class="hidden">
                    <div id="dropZoneContent">
                        <div class="ai-icon-float mb-3 inline-block">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-100 to-blue-100 flex items-center justify-center mx-auto">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="1.5">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <path d="M9 15l2 2 4-4"/>
                                </svg>
                            </div>
                        </div>
                        <p class="text-gray-700 font-semibold text-sm mb-1">วาง PDF หรือรูปภาพที่นี่ หรือคลิกเพื่อเลือกไฟล์</p>
                        <p id="dropZoneSubText" class="text-gray-400 text-xs">รองรับ PDF, JPG, PNG, WEBP — ระบบจะอ่านและกรอกข้อมูลให้อัตโนมัติ</p>
                    </div>
                    <!-- Scanning State -->
                    <div id="scanningState" class="hidden">
                        <div class="scan-container w-24 h-24 mx-auto mb-3 rounded-xl bg-gradient-to-br from-purple-50 to-blue-50 border-2 border-purple-200 flex items-center justify-center">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                            <div class="scan-line"></div>
                        </div>
                        <p id="scanningMsg1" class="text-purple-700 font-semibold text-sm mb-1">กำลังวิเคราะห์ด้วยระบบ OCR...</p>
                        <p id="scanningMsg2" class="text-purple-400 text-xs">กรุณารอสักครู่ ระบบกำลังอ่านภาษาไทย</p>
                    </div>
                </div>

                <!-- AI Result Panel -->
                <div id="aiResult" class="hidden ai-result-badge rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-green-500 text-lg">✅</span>
                            <span class="font-semibold text-gray-800 text-sm"><span id="aiSuccessMethod">OCR</span> อ่านข้อมูลสำเร็จ — กรอกแบบฟอร์มแล้ว!</span>
                        </div>
                        <button onclick="clearAiResult()" class="text-gray-400 hover:text-gray-600 text-xs px-2 py-1 rounded hover:bg-gray-100 transition">
                            ✕ ล้าง
                        </button>
                    </div>
                    <div class="space-y-1.5 text-xs text-gray-600">
                        <div class="flex gap-2">
                            <span class="text-purple-500 font-medium w-28 shrink-0">🏢 หน่วยงาน:</span>
                            <span id="resultDocFrom" class="text-gray-800"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-purple-500 font-medium w-28 shrink-0">📄 เรื่อง:</span>
                            <span id="resultDocName" class="text-gray-800"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-purple-500 font-medium w-28 shrink-0">📁 ประเภท:</span>
                            <span id="resultDocType" class="text-gray-800"></span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                            <span>ความแม่นยำประมาณการ</span>
                            <span id="confidenceText">0%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1">
                            <div id="confidenceBar" class="confidence-bar w-0 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <!-- Error Panel -->
                <div id="aiError" class="hidden bg-red-50 border border-red-200 rounded-xl p-4">
                    <div class="flex items-center gap-2 text-red-700 text-sm">
                        <span>⚠️</span>
                        <span id="aiErrorMsg">เกิดข้อผิดพลาด</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Form Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-brand-600 px-6 py-4">
                <h4 class="text-white font-bold text-lg flex items-center gap-2">
                    <i class="bi bi-file-earmark-plus"></i> ข้อมูลหนังสือราชการ
                </h4>
            </div>
            <div class="p-6">
                <form id="docForm" method="POST" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                หน่วยงานต้นทาง <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="field_doc_from" name="doc_from"
                                   placeholder="เช่น วิทยาลัยเทคนิคเลย" required
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all">
                            <!-- OCR Candidate Tags -->
                            <div id="doc_from_candidates" class="hidden mt-2">
                                <p class="text-xs text-gray-400 mb-1.5">💡 ตัวเลือกจาก OCR — คลิกเพื่อเลือก:</p>
                                <div id="doc_from_tags" class="flex flex-wrap gap-1.5"></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                ประเภทหนังสือ <span class="text-red-500">*</span>
                            </label>
                            <select id="field_doc_type_id" name="doc_type_id" required
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all">
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            เรื่อง (ชื่อเอกสาร) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="field_doc_name" name="doc_name"
                               placeholder="ชื่อหนังสือราชการ" required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all">
                        <!-- OCR Candidate Tags for doc_name -->
                        <div id="doc_name_candidates" class="hidden mt-2">
                            <p class="text-xs text-gray-400 mb-1.5">💡 ตัวเลือกจาก OCR — คลิกเพื่อเลือก:</p>
                            <div id="doc_name_tags" class="flex flex-wrap gap-1.5"></div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            ไฟล์หนังสือราชการ (เฉพาะ PDF) <span class="text-red-500">*</span>
                        </label>
                        <input type="file" id="field_doc_files" name="doc_files[]" multiple accept="application/pdf,image/jpeg,image/png,image/gif,image/webp,image/tiff" required
                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                        <p class="text-xs text-gray-400 mt-1">สามารถเลือกได้หลายไฟล์ รองรับ PDF, JPG, PNG, WEBP</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ผู้อัปโหลด</label>
                        <input type="text" value="<?php echo htmlspecialchars($_SESSION['fullname']); ?>" disabled
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-gray-500">
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

<!-- PDF Preview Panel -->
<div id="pdfPreviewPanel" class="hidden max-w-3xl mx-auto mt-0 pb-4">
    <div class="rounded-xl overflow-hidden shadow-sm border border-gray-200">
        <div class="bg-gray-700 px-5 py-2.5 flex items-center justify-between">
            <div class="flex items-center gap-2 text-white">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span class="font-semibold text-sm">พรีวิวหน้าแรก — ดูเพื่อกรอกข้อมูลเอง</span>
            </div>
            <button onclick="togglePreview()" id="previewToggleBtn"
                    class="text-gray-300 hover:text-white text-xs px-2 py-1 bg-white/10 hover:bg-white/20 rounded transition flex items-center gap-1">
                <svg id="previewChevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                     style="transition:transform 0.25s;"><polyline points="18 15 12 9 6 15"/></svg>
                <span id="previewToggleText">ซ่อน</span>
            </button>
        </div>
        <div id="pdfPreviewBody" class="bg-gray-100 overflow-auto flex justify-center p-4" style="max-height:80vh;">
            <canvas id="pdfPreviewCanvas" class="shadow-lg rounded" style="background:#fff;"></canvas>
        </div>
    </div>
</div>

<canvas id="pdfCanvas" class="hidden"></canvas>

<script>
const DOC_TYPES = <?php echo json_encode($docTypesForJs); ?>;

let currentMethod = 'ocr';

function setMethod(m) {
    currentMethod = m;
    const ocrBtn    = document.getElementById('methodOcr');
    const geminiBtn = document.getElementById('methodGemini');
    if (m === 'ocr') {
        ocrBtn.classList.add('bg-white/25', 'text-white');
        ocrBtn.classList.remove('text-white/60');
        geminiBtn.classList.remove('bg-white/25', 'text-white');
        geminiBtn.classList.add('text-white/60');
        document.getElementById('scanMethodTitle').textContent  = 'ระบบอ่านข้อมูล OCR — อ่านข้อมูลจาก PDF อัตโนมัติ';
        document.getElementById('dropZoneSubText').textContent  = 'ระบบ OCR จะอ่านหน้าแรกของ PDF และกรอกข้อมูลให้อัตโนมัติ';
        document.getElementById('scanningMsg1').textContent     = 'กำลังวิเคราะห์ด้วยระบบ OCR...';
        document.getElementById('scanningMsg2').textContent     = 'กรุณารอสักครู่ ระบบกำลังอ่านภาษาไทย';
    } else {
        geminiBtn.classList.add('bg-white/25', 'text-white');
        geminiBtn.classList.remove('text-white/60');
        ocrBtn.classList.remove('bg-white/25', 'text-white');
        ocrBtn.classList.add('text-white/60');
        document.getElementById('scanMethodTitle').textContent  = 'ระบบ Gemini AI — วิเคราะห์เอกสาร PDF อัตโนมัติ';
        document.getElementById('dropZoneSubText').textContent  = 'Gemini AI จะวิเคราะห์หน้าแรกของ PDF และกรอกข้อมูลให้อัตโนมัติ';
        document.getElementById('scanningMsg1').textContent     = 'กำลังวิเคราะห์ด้วย Gemini AI...';
        document.getElementById('scanningMsg2').textContent     = 'กรุณารอสักครู่ AI กำลังวิเคราะห์เอกสาร';
    }
}

/* ───── Drag & Drop ───── */
const dropZone = document.getElementById('aiDropZone');
['dragenter','dragover'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.add('drag-over'); }));
['dragleave','drop'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.remove('drag-over'); }));
const ALLOWED_TYPES = ['application/pdf','image/jpeg','image/png','image/gif','image/webp','image/tiff'];

dropZone.addEventListener('drop', ev => {
    const file = ev.dataTransfer.files[0];
    if (file && ALLOWED_TYPES.includes(file.type)) {
        const dt = new DataTransfer();
        for (let i = 0; i < ev.dataTransfer.files.length; i++) {
            if (ALLOWED_TYPES.includes(ev.dataTransfer.files[i].type)) dt.items.add(ev.dataTransfer.files[i]);
        }
        document.getElementById('field_doc_files').files = dt.files;
        processFile(file);
    } else {
        showAiError('รองรับเฉพาะ PDF, JPG, PNG, WEBP เท่านั้น');
    }
});

document.getElementById('aiPdfInput').addEventListener('change', function () {
    if (this.files[0]) {
        const dt = new DataTransfer();
        for (let i = 0; i < this.files.length; i++) dt.items.add(this.files[i]);
        document.getElementById('field_doc_files').files = dt.files;
        processFile(this.files[0]);
    }
});

/* เมื่อเลือกไฟล์ในฟอร์มหลัก → auto scan ไฟล์แรก */
document.getElementById('field_doc_files').addEventListener('change', function () {
    const firstFile = Array.from(this.files).find(f => ALLOWED_TYPES.includes(f.type));
    if (firstFile) processFile(firstFile);
});

async function processFile(file) {
    setScanningState(true);
    hideAiResult();
    hideAiError();

    try {
        let imageBase64, mimeType;
        const previewCanvas = document.getElementById('pdfPreviewCanvas');
        const previewCtx = previewCanvas.getContext('2d');

        if (file.type === 'application/pdf') {
            // PDF → render page 1 ด้วย PDF.js
            const arrayBuffer = await file.arrayBuffer();
            const pdfDoc = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            const page   = await pdfDoc.getPage(1);
            const viewport = page.getViewport({ scale: 2.0 });

            // Hidden canvas สำหรับส่ง OCR/AI
            const canvas = document.getElementById('pdfCanvas');
            canvas.width = viewport.width; canvas.height = viewport.height;
            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
            imageBase64 = canvas.toDataURL('image/png').split(',')[1];
            mimeType = 'image/png';

            // Preview canvas
            previewCanvas.width  = viewport.width;
            previewCanvas.height = viewport.height;
            await page.render({ canvasContext: previewCtx, viewport }).promise;

        } else {
            // รูปภาพ → อ่าน base64 โดยตรง
            imageBase64 = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = e => resolve(e.target.result.split(',')[1]);
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
            mimeType = file.type;

            // Preview: วาดบน canvas
            const img = new Image();
            const objectUrl = URL.createObjectURL(file);
            await new Promise((resolve, reject) => { img.onload = resolve; img.onerror = reject; img.src = objectUrl; });
            previewCanvas.width  = img.naturalWidth;
            previewCanvas.height = img.naturalHeight;
            previewCtx.drawImage(img, 0, 0);
            URL.revokeObjectURL(objectUrl);
        }

        // แสดง preview panel
        document.getElementById('pdfPreviewPanel').classList.remove('hidden');
        document.getElementById('pdfPreviewBody').style.display = 'flex';
        document.getElementById('previewChevron').style.transform = '';
        document.getElementById('previewToggleText').textContent = 'ซ่อน';

        const response = await fetch('api/extract_pdf_info.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image_base64: imageBase64, mime_type: mimeType, method: currentMethod })
        });
        const data = await response.json();

        setScanningState(false);

        if (!data.success) {
            showAiError(data.error || 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ');
            return;
        }

        fillFormWithAnimation(data);

    } catch (err) {
        setScanningState(false);
        showAiError('เกิดข้อผิดพลาด: ' + err.message);
    }
}

async function fillFormWithAnimation(data) {
    document.getElementById('aiSuccessMethod').textContent = data.method === 'gemini' ? 'Gemini AI' : 'OCR';
    /* แสดง result badge */
    document.getElementById('resultDocFrom').textContent = data.doc_from || '(ไม่พบข้อมูล)';
    document.getElementById('resultDocName').textContent = data.doc_name || '(ไม่พบข้อมูล)';
    document.getElementById('resultDocType').textContent = data.doc_type_guess || '(ไม่พบข้อมูล)';

    const conf = Math.round((data.confidence || 0) * 100);
    document.getElementById('confidenceText').textContent = conf + '%';
    document.getElementById('aiResult').classList.remove('hidden');
    setTimeout(() => {
        document.getElementById('confidenceBar').style.width = conf + '%';
    }, 100);

    /* แสดง Candidate Tags สำหรับหน่วยงาน */
    const candidates = data.doc_from_candidates || [];
    const tagContainer = document.getElementById('doc_from_candidates');
    const tagBox = document.getElementById('doc_from_tags');
    tagBox.innerHTML = '';
    if (candidates.length > 0) {
        candidates.forEach((text, idx) => {
            const tag = document.createElement('button');
            tag.type = 'button';
            tag.className = 'ocr-candidate-tag' + (idx === 0 ? ' active' : '');
            tag.title = text;
            tag.textContent = text;
            tag.addEventListener('click', () => {
                document.getElementById('field_doc_from').value = text;
                // ไฮไลต์ tag ที่เลือก
                tagBox.querySelectorAll('.ocr-candidate-tag').forEach(t => t.classList.remove('active'));
                tag.classList.add('active');
            });
            tagBox.appendChild(tag);
        });
        tagContainer.classList.remove('hidden');
    } else {
        tagContainer.classList.add('hidden');
    }

    /* แสดง Candidate Tags สำหรับชื่อเรื่อง (doc_name) */
    const nameCandidates = data.doc_name_candidates || [];
    const nameTagContainer = document.getElementById('doc_name_candidates');
    const nameTagBox = document.getElementById('doc_name_tags');
    nameTagBox.innerHTML = '';
    if (nameCandidates.length > 0) {
        nameCandidates.forEach((text, idx) => {
            const tag = document.createElement('button');
            tag.type = 'button';
            tag.className = 'ocr-candidate-tag' + (idx === 0 ? ' active' : '');
            tag.title = text;
            tag.textContent = text;
            tag.addEventListener('click', () => {
                document.getElementById('field_doc_name').value = text;
                nameTagBox.querySelectorAll('.ocr-candidate-tag').forEach(t => t.classList.remove('active'));
                tag.classList.add('active');
            });
            nameTagBox.appendChild(tag);
        });
        nameTagContainer.classList.remove('hidden');
    } else {
        nameTagContainer.classList.add('hidden');
    }

    /* Typing animation สำหรับ text inputs */
    if (data.doc_from) await typeIntoField('field_doc_from', data.doc_from);
    if (data.doc_name) await typeIntoField('field_doc_name', data.doc_name);

    /* Auto-select ประเภทหนังสือ */
    if (data.doc_type_guess) {
        const selectEl = document.getElementById('field_doc_type_id');
        const guess = data.doc_type_guess.toLowerCase();
        let bestMatch = null, bestScore = 0;
        DOC_TYPES.forEach(dt => {
            const name = dt.name.toLowerCase();
            const score = guess === name ? 100
                : name.includes(guess) || guess.includes(name) ? 70
                : [...guess].filter(c => name.includes(c)).length;
            if (score > bestScore) { bestScore = score; bestMatch = dt.id; }
        });
        if (bestMatch && bestScore > 20) {
            selectEl.value = bestMatch;
            selectEl.classList.add('field-highlight');
            setTimeout(() => selectEl.classList.remove('field-highlight'), 2500);
        }
    }
}

async function typeIntoField(fieldId, text) {
    const field = document.getElementById(fieldId);
    field.value = '';
    field.classList.add('field-highlight', 'typing-cursor');
    field.focus();
    for (let i = 0; i <= text.length; i++) {
        field.value = text.substring(0, i);
        await new Promise(r => setTimeout(r, 18));
    }
    field.classList.remove('typing-cursor');
    setTimeout(() => field.classList.remove('field-highlight'), 1500);
    await new Promise(r => setTimeout(r, 50));
}

function setScanningState(scanning) {
    document.getElementById('dropZoneContent').classList.toggle('hidden', scanning);
    document.getElementById('scanningState').classList.toggle('hidden', !scanning);
}
function hideAiResult() { document.getElementById('aiResult').classList.add('hidden'); }
function hideAiError()  { document.getElementById('aiError').classList.add('hidden'); }
function showAiError(msg) {
    document.getElementById('aiErrorMsg').textContent = msg;
    document.getElementById('aiError').classList.remove('hidden');
}
function clearAiResult() {
    hideAiResult();
    document.getElementById('field_doc_from').value = '';
    document.getElementById('field_doc_name').value = '';
    document.getElementById('field_doc_type_id').value = '';
    document.getElementById('aiPdfInput').value = '';
    document.getElementById('field_doc_files').value = '';
    // ซ่อน candidate tags
    document.getElementById('doc_from_tags').innerHTML = '';
    document.getElementById('doc_from_candidates').classList.add('hidden');
    document.getElementById('doc_name_tags').innerHTML = '';
    document.getElementById('doc_name_candidates').classList.add('hidden');
    // ซ่อน preview
    document.getElementById('pdfPreviewPanel').classList.add('hidden');
    const pc = document.getElementById('pdfPreviewCanvas');
    pc.getContext('2d').clearRect(0, 0, pc.width, pc.height);
}

let previewOpen = true;
function togglePreview() {
    previewOpen = !previewOpen;
    const body = document.getElementById('pdfPreviewBody');
    const chevron = document.getElementById('previewChevron');
    const label = document.getElementById('previewToggleText');
    if (previewOpen) {
        body.style.display = 'flex';
        chevron.style.transform = '';
        label.textContent = 'ซ่อน';
    } else {
        body.style.display = 'none';
        chevron.style.transform = 'rotate(180deg)';
        label.textContent = 'แสดง';
    }
}

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
