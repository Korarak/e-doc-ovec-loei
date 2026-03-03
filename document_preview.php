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
$doc_query = $conn->prepare("SELECT doc_no, doc_upload_date FROM documents WHERE doc_id = ?");
$doc_query->bind_param("i", $doc_id);
$doc_query->execute();
$doc_query->bind_result($doc_no, $doc_upload_date);
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
$sign_query->bind_param("iii", $doc_id,$file_id, $page_num);
$sign_query->execute();
$sign_details = $sign_query->get_result()->fetch_all(MYSQLI_ASSOC);
$sign_query->close();

// Use the user's signature from the session
$signatureImage = isset($_SESSION['sign']) ? $_SESSION['sign'] : null;
?>
<div class="py-2">
    <!-- Header & Controls -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="bi bi-eye text-brand-600"></i> ดูเอกสาร: <?php echo htmlspecialchars($doc_no); ?>
            </h2>
        </div>
        <div class="flex items-center gap-3">
            <button id="prev-page" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors flex items-center gap-2">
                <i class="bi bi-chevron-left"></i> ก่อนหน้า
            </button>
            <span class="text-sm font-medium text-gray-600 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200">
                หน้า: <span id="page-info" class="text-brand-600 font-bold"></span>
            </span>
            <button id="next-page" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors flex items-center gap-2">
                ถัดไป <i class="bi bi-chevron-right"></i>
            </button>
            <a href="javascript:history.back()" class="bg-slate-800 hover:bg-slate-700 text-white font-medium py-2 px-4 rounded-lg transition-colors ml-2 shadow-sm">
                ย้อนกลับ
            </a>
        </div>
    </div>

    <!-- Document Viewer -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex justify-center p-6 bg-gray-50/50">
        <div id="pdf-container" class="relative inline-block shadow-lg border border-gray-200 bg-white" style="width: 210mm; height: 297mm; overflow: hidden;">
            <canvas id="pdf-canvas" class="w-full h-full"></canvas>
            <?php foreach ($sign_details as $detail): ?>
                <div class="absolute bg-white/25 border-0 p-[2px] leading-relaxed font-sans whitespace-wrap text-[14px] pointer-events-none" style="left:<?= $detail['x_pos'] ?>px; top: <?= $detail['y_pos'] ?>px; font-family: 'Sarabun', sans-serif; color: #003399;">
                    <?= nl2br(htmlspecialchars($detail['sign_txt'])) ?><br>
                    <?php if (!empty($detail['sign_pic'])): ?>
                        <img src="<?= htmlspecialchars($detail['sign_pic']) ?>" alt="" width="70" class="mt-1">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div id="preview-text" class="absolute bg-white/25 border-0 p-[2px] leading-relaxed font-sans whitespace-pre-wrap text-[14px] hidden" style="font-family: 'Sarabun', sans-serif; color: #003399;"></div>
            <div id="preview-date" class="absolute hidden"></div>
            <div id="preview-sign" class="absolute w-[50px] h-[50px] bg-contain bg-no-repeat hidden"></div>
        </div>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap');
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.5.207/pdf.min.js"></script>
<script>
    let pdfDoc = null,
        pageNum = <?= $page_num ?>,
        pageCount = 0,
        canvas = document.getElementById('pdf-canvas'),
        ctx = canvas.getContext('2d'),
        previewText = document.getElementById('preview-text'),
        previewDate = document.getElementById('preview-date'),
        previewSign = document.getElementById('preview-sign');

    document.getElementById('prev-page').addEventListener('click', function() {
        if (pageNum <= 1) return;
        pageNum--;
        window.location.href = `document_preview.php?doc_id=<?= $doc_id ?>&file_id=<?= $file_id ?>&page=${pageNum}`;
    });

    document.getElementById('next-page').addEventListener('click', function() {
        if (pageNum >= pageCount) return;
        pageNum++;
        window.location.href = `document_preview.php?doc_id=<?= $doc_id ?>&file_id=<?= $file_id ?>&page=${pageNum}`;
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
</script>

<?php
$content = ob_get_clean();
include 'base.php';
?>