<?php
session_start();
include('edoc-db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$doc_id = $_GET['doc_id'];
$page_num = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Fetch document details
$doc_query = $conn->prepare("SELECT doc_files, doc_no FROM documents WHERE doc_id = ?");
$doc_query->bind_param("i", $doc_id);
$doc_query->execute();
$doc_query->bind_result($doc_file, $doc_no);
$doc_query->fetch();
$doc_query->close();

// Fetch sign details
$sign_query = $conn->prepare("
    SELECT sign_txt, sign_datetime, x_pos, y_pos, page_num 
    FROM sign_detail 
    WHERE sign_doc_id = (
        SELECT sign_doc_id FROM sign_doc WHERE doc_id = ? LIMIT 1
    ) 
    AND page_num = ?
");
$sign_query->bind_param("ii", $doc_id, $page_num);
$sign_query->execute();
$sign_details = $sign_query->get_result()->fetch_all(MYSQLI_ASSOC);
$sign_query->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Document Details</title>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap');
        #pdf-canvas {
            border: 1px solid black;
            width: 210mm;
            height: 297mm;
            font-family: 'Sarabun', sans-serif;
        }
        .annotation {
            position: absolute;
            background-color: rgba(255, 255, 255, 0.75);
            border: 1px solid black;
            padding: 2px;
            line-height: 1; /* ปรับระยะห่างระหว่างบรรทัด */
            font-family: 'Sarabun', sans-serif;
            white-space: wrap;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.5.207/pdf.min.js"></script>
</head>
<body>
    <div id="pdf-container" style="position: relative;">
        <canvas id="pdf-canvas"></canvas>
        <?php foreach ($sign_details as $detail): ?>
            <div class="annotation" style="left:<?= $detail['x_pos'] ?>px; top: <?= $detail['y_pos'] ?>px;">
                <?= nl2br(htmlspecialchars($detail['sign_txt'])) ?><br>
                รับที่: <?= htmlspecialchars($doc_no) ?><br>
                วันที่: <?= date('d/m/Y', strtotime($detail['sign_datetime'])) ?><br>
                เวลา: <?= date('H:i:s', strtotime($detail['sign_datetime'])) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div>
        <button id="prev-page">Previous Page</button>
        <button id="next-page">Next Page</button>
        <span>Page: <span id="page-info"><?= $page_num ?></span></span>
    </div>

    <script>
        let pdfDoc = null,
            pageNum = <?= $page_num ?>,
            pageCount = 0,
            canvas = document.getElementById('pdf-canvas'),
            ctx = canvas.getContext('2d');

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

        document.getElementById('prev-page').addEventListener('click', function() {
            if (pageNum <= 1) {
                return;
            }
            pageNum--;
            window.location.href = `sarabun_doc_detail.php?doc_id=<?= $doc_id ?>&page=${pageNum}`;
        });

        document.getElementById('next-page').addEventListener('click', function() {
            if (pageNum >= pageCount) {
                return;
            }
            pageNum++;
            window.location.href = `sarabun_doc_detail.php?doc_id=<?= $doc_id ?>&page=${pageNum}`;
        });
    </script>
</body>
</html>
