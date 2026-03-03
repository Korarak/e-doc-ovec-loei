<?php
ob_start();
include('edoc-db.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$doc_id = $_GET['doc_id'];
$file_id = $_GET['file_id'];
$page_num = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Fetch document details
$doc_query = $conn->prepare("SELECT doc_no, doc_upload_date , doc_type_id FROM documents WHERE doc_id = ?");
$doc_query->bind_param("i", $doc_id);
$doc_query->execute();
$doc_query->bind_result($doc_no, $doc_upload_date ,$doc_type_id);
$doc_query->fetch();
$doc_query->close();

// Fetch the first document file for rendering
$file_query = $conn->prepare("SELECT file_path FROM document_files WHERE doc_id = ? and file_id = ? LIMIT 1");
$file_query->bind_param("ii", $doc_id,$file_id);
$file_query->execute();
$file_query->bind_result($doc_file);
$file_query->fetch();
$file_query->close();

// Fetch sign details
$sign_query = $conn->prepare("
    SELECT *
    FROM sign_detail 
    WHERE sign_doc_id = (
        SELECT sign_doc_id FROM sign_doc WHERE doc_id = ? LIMIT 1
    ) 
    AND sign_file_id = ?
    AND page_num = ?
");
$sign_query->bind_param("iii", $doc_id, $file_id , $page_num);
$sign_query->execute();
$sign_details = $sign_query->get_result()->fetch_all(MYSQLI_ASSOC);
$sign_query->close();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Text to PDF</title>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap');
        #pdf-canvas {
            border: 1px solid black;
            width: 210mm;
            height: 297mm;
            font-family: 'Sarabun', sans-serif;
        }
        #preview-text, #preview-sign, #preview-date {
            position: absolute;
            background-color: rgba(255, 255, 255, 0.25);
            border: 0px solid black;
            padding: 2px;
            line-height: 1;
            font-family: 'Sarabun', sans-serif;
            white-space: pre-wrap;
            color: #003399; /* Blue Ink */
        }
        #preview-sign {
            width: 50px;
            height: 50px;
            background-size: contain;
            background-repeat: no-repeat;
        }
        .annotation {
            position: absolute;
            background-color: rgba(255, 255, 255, 0.25);
            border: 1px dashed transparent;
            padding: 2px;
            line-height: 1;
            font-family: 'Sarabun', sans-serif;
            white-space: wrap;
            color: #003399; /* Blue Ink */
        }
        .annotation:hover {
            border-color: #cbd5e1;
            background-color: rgba(255, 255, 255, 0.5);
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.5.207/pdf.min.js"></script>

            <?php
                //echo "เวลา " . formatThaiTime(date('Y-m-d H:i:s'));
                $now_date = formatThaiDate(date('Y-m-d'));
                $now_time = formatThaiTime(date('H:i'));
            ?>
</head>
<body>
<div class="py-4">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="bi bi-pen text-brand-600"></i> ลงนามสารบรรณ
        </h2>
        <a href="dashboard.php" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium py-2 px-4 rounded-lg inline-flex items-center gap-2 transition-all shadow-sm">
            <i class="bi bi-arrow-left"></i> ย้อนกลับ
        </a>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left Panel: Controls -->
        <div class="lg:col-span-4 space-y-6">
            <!-- Select Form -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <label for="formSelect" class="block text-sm font-medium text-gray-700 mb-2">เลือกฟอร์ม:</label>
                <select id="formSelect" class="form-select w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500">
                    <option value="0">--กรุณาเลือกตราปั้ม--</option>
                    <option value="1">ตราปั้มลงรับหนังสือภายนอก และบันทึกข้อมูล</option>
                    <option value="2">ตราปั้มลงเลขคำสั่ง ประกาศ</option>
                </select>
            </div>

            <!-- Form 1 -->
            <div id="form1" style="display:none;" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-all duration-300">
                <form id="text-form-1" method="post" action="sarabun_generate_1.php" class="space-y-4">
                    <div>
                        <label for="input-text-1" class="block text-sm font-medium text-gray-700 mb-2">ตัวอย่างข้อความ</label>
                        <textarea id="input-text-1" name="input-text" class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" rows="4" required>วิทยาลัยเทคนิคเลย
รับที่: <?php echo htmlspecialchars($doc_no), "\n"; ?>
วันที่: <?= $now_date, "\n" ?>
เวลา: <?= $now_time, "\n" ?></textarea>
                    </div>

                    <div>
                        <label for="dep_select" class="block text-sm font-medium text-gray-700 mb-2">ส่งต่อฝ่าย (รอง ผอ.) <span class="text-red-500">*</span></label>
                        <select id="dep_select" name="dep-id" class="form-select w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" required>
                            <option value="" disabled selected>-- เลือกฝ่ายที่รับผิดชอบ --</option>
                            <?php
                            $inst_id = $_SESSION['inst_id'];
                            $dep_q = $conn->prepare("SELECT dep_id, dep_name FROM department WHERE inst_id = ? ORDER BY dep_id");
                            $dep_q->bind_param("i", $inst_id);
                            $dep_q->execute();
                            $dep_res = $dep_q->get_result();
                            while ($dep = $dep_res->fetch_assoc()):
                            ?>
                                <option value="<?= $dep['dep_id'] ?>"><?= htmlspecialchars($dep['dep_name']) ?></option>
                            <?php endwhile; 
                            $dep_q->close();
                            ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="x-pos-1" class="block text-sm font-medium text-gray-700 mb-1">X Position:</label>
                            <input type="text" id="x-pos-1" name="x-pos" class="form-control w-full bg-gray-50 border-gray-300 rounded-lg text-sm text-gray-500 cursor-not-allowed" readonly>
                        </div>
                        <div>
                            <label for="y-pos-1" class="block text-sm font-medium text-gray-700 mb-1">Y Position:</label>
                            <input type="text" id="y-pos-1" name="y-pos" class="form-control w-full bg-gray-50 border-gray-300 rounded-lg text-sm text-gray-500 cursor-not-allowed" readonly>
                        </div>
                    </div>

                    <input type="hidden" id="page-num" name="page-num" value="<?= $page_num ?>">
                    <input type="hidden" name="doc_id" value="<?= $doc_id ?>">
                    <input type="hidden" name="file_id" value="<?= $file_id ?>">
                    
                    <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 rounded-lg transition-colors flex justify-center items-center gap-2 shadow-sm">
                        <i class="bi bi-save"></i> ลงรับเอกสาร
                    </button>
                </form>
            </div>

            <!-- Form 2 -->
            <div id="form2" style="display:none;" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-all duration-300">
                <form id="text-form-2" method="post" action="sarabun_generate_1.php" class="space-y-4">
                    <div>
                        <label for="input-text-2" class="block text-sm font-medium text-gray-700 mb-2">ตัวอย่างข้อความ</label>
                        <textarea id="input-text-2" name="input-text" class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" rows="4" required><?php echo htmlspecialchars($doc_no), "\n"; ?></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="x-pos-2" class="block text-sm font-medium text-gray-700 mb-1">X Position:</label>
                            <input type="text" id="x-pos-2" name="x-pos" class="form-control w-full bg-gray-50 border-gray-300 rounded-lg text-sm text-gray-500 cursor-not-allowed" readonly>
                        </div>
                        <div>
                            <label for="y-pos-2" class="block text-sm font-medium text-gray-700 mb-1">Y Position:</label>
                            <input type="text" id="y-pos-2" name="y-pos" class="form-control w-full bg-gray-50 border-gray-300 rounded-lg text-sm text-gray-500 cursor-not-allowed" readonly>
                        </div>
                    </div>

                    <input type="hidden" id="page-num-2" name="page-num" value="<?= $page_num ?>">
                    <input type="hidden" name="doc_id" value="<?= $doc_id ?>">
                    <input type="hidden" name="file_id" value="<?= $file_id ?>">
                    
                    <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 rounded-lg transition-colors flex justify-center items-center gap-2 shadow-sm">
                        <i class="bi bi-save"></i> ลงรับเอกสาร
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Panel: PDF Viewer -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 lg:p-6">
                <div class="flex justify-between items-center mb-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                    <span class="text-sm font-medium text-gray-600 flex items-center">
                        <i class="bi bi-file-earmark-pdf text-red-500 mr-2 text-lg"></i>ตัวอย่างเอกสาร
                    </span>
                    <div class="flex items-center gap-3">
                        <button id="prev-page" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm font-medium transition-colors shadow-sm">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span class="text-sm font-medium text-gray-600">หน้า: <span id="page-info" class="text-brand-600 font-bold"></span></span>
                        <button id="next-page" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm font-medium transition-colors shadow-sm">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="overflow-auto border border-gray-200 rounded-xl bg-gray-100 flex justify-center p-4">
                    <div id="pdf-container" style="position: relative;" class="shadow-md bg-white">
                        <canvas id="pdf-canvas"></canvas>
                        <?php foreach ($sign_details as $detail): ?>
                            <div class="annotation group" style="left:<?= $detail['x_pos'] ?>px; top: <?= $detail['y_pos'] ?>px;">
                                <?= nl2br(htmlspecialchars($detail['sign_txt'])) ?><br>
                                <a href="delete_sign_detail.php?id=<?= $detail['detail_id'] ?>" 
                                   class="hidden group-hover:flex absolute -top-2 -right-2 bg-red-500 text-white w-5 h-5 items-center justify-center rounded-full text-[10px] shadow-sm hover:bg-red-600 transition-colors" 
                                   title="ลบตราปั้มนี้ (Liquid Paper)"
                                   onclick="return confirm('ต้องการลบตราปั้มนี้ใช่หรือไม่?')">
                                    <i class="bi bi-x"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                        <div id="preview-text"></div>
                        <div id="preview-date"></div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>
<!-- jszone -->

<script>
document.getElementById('formSelect').addEventListener('change', function() {
    var form1 = document.getElementById('form1');
    var form2 = document.getElementById('form2');
    var selectedValue = this.value;

    // ซ่อนฟอร์มทั้งหมด
    form1.style.display = 'none';
    form2.style.display = 'none';

    // แสดงฟอร์มที่เลือก
    if (selectedValue === '1') {
        form1.style.display = 'block';
    } else if (selectedValue === '2') {
        form2.style.display = 'block';
    }
});
    </script>

<script>
    let pdfDoc = null,
        pageNum = <?= $page_num ?>,
        pageCount = 0,
        canvas = document.getElementById('pdf-canvas'),
        ctx = canvas.getContext('2d'),
        previewText = document.getElementById('preview-text'),
        previewDate = document.getElementById('preview-date');

    document.getElementById('prev-page').addEventListener('click', function() {
        if (pageNum <= 1) return;
        pageNum--;
        window.location.href = `sarabun_sign.php?doc_id=<?= $doc_id ?>&file_id=<?= $file_id ?>&page=${pageNum}`;
    });

    document.getElementById('next-page').addEventListener('click', function() {
        if (pageNum >= pageCount) return;
        pageNum++;
        window.location.href = `sarabun_sign.php?doc_id=<?= $doc_id ?>&file_id=<?= $file_id ?>&page=${pageNum}`;
    });

    canvas.addEventListener('click', function(event) {
        let rect = canvas.getBoundingClientRect();
        let x = event.clientX - rect.left;
        let y = event.clientY - rect.top;

        document.getElementById('x-pos').value = x;
        document.getElementById('y-pos').value = y;

        let inputText = document.getElementById('input-text').value;
        if (inputText) {
            previewText.style.left = `${x}px`;
            previewText.style.top = `${y}px`;
            previewText.textContent = inputText;
            previewText.style.display = 'block';
        }
    });

    function renderPage(num) {
        pdfDoc.getPage(num).then(function(page) {
            let viewport = page.getViewport({ scale: 2 });
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            let renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            page.render(renderContext);
            document.getElementById('page-info').textContent = `${num} / ${pageCount}`;
        });
    }

    function loadPDF(url) {
        pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
            pdfDoc = pdfDoc_;
            pageCount = pdfDoc.numPages;
            renderPage(pageNum);
        });
    }

    loadPDF('<?= $doc_file ?>');

    document.addEventListener('DOMContentLoaded', function() {
    const formSelect = document.getElementById('formSelect');
    let currentForm = 'form1'; // เริ่มต้นเป็นฟอร์ม 1

    // เปลี่ยน currentForm ตามฟอร์มที่ถูกเลือก
    formSelect.addEventListener('change', function() {
        if (this.value === '1') {
            currentForm = 'form1';
        } else if (this.value === '2') {
            currentForm = 'form2';
        }
    });

    // เพิ่ม event listener สำหรับการคลิกที่ canvas
    document.getElementById('pdf-canvas').addEventListener('click', function(event) {
        let rect = this.getBoundingClientRect();
        let x = event.clientX - rect.left;
        let y = event.clientY - rect.top;
        
        let idSuffix = currentForm === 'form1' ? '-1' : '-2';

        // อัปเดตพิกัดลงในฟอร์มที่ถูกเลือก
        document.querySelector(`#x-pos${idSuffix}`).value = x;
        document.querySelector(`#y-pos${idSuffix}`).value = y;

        // อัปเดต preview text ในตำแหน่งที่คลิก
        let inputText = document.querySelector(`#input-text${idSuffix}`).value;
        if (inputText) {
            let previewText = document.getElementById('preview-text');
            previewText.style.left = `${x}px`;
            previewText.style.top = `${y}px`;
            previewText.textContent = inputText;
            previewText.style.display = 'block';
        }
    });

    // ตรวจสอบการกรอกข้อมูลก่อนส่งฟอร์ม
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(event) {
            const xPos = form.querySelector('[name="x-pos"]').value;
            const yPos = form.querySelector('[name="y-pos"]').value;

            // ถ้าไม่ได้ระบุพิกัด ให้แสดงข้อความเตือนและไม่ส่งฟอร์ม
            if (xPos.trim() === '' || yPos.trim() === '') {
                alert('กรุณาคลิกตำแหน่งบนหน้ากระดาษ');
                event.preventDefault();
            }
        });
    });
});
</script>

</body>
</html>

<?php
$content = ob_get_clean();
include 'base.php';
?>