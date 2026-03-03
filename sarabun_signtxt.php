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
<!DOCTYPE html>
<html>
<head>
    <title>เกษียณโดยงานสารบรรณ</title>
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
            line-height: 1.5;
            font-family: 'Sarabun', sans-serif;
            white-space: pre-wrap;
            font-size: 14px; /* Add this line to set the font size */
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
            line-height: 1.5;
            font-family: 'Sarabun', sans-serif;
            white-space: wrap;
            font-size: 14px; /* Add this line to set the font size */
            color: #003399; /* Blue Ink */
        }
        .annotation:hover {
            border-color: #cbd5e1;
            background-color: rgba(255, 255, 255, 0.5);
        }
    </style>
    <style>
        #signatureImage {
            display: none;
            margin-top: 10px;
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
            <i class="bi bi-pen text-brand-600"></i> เกษียณโดยงานสารบรรณ
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
                    <option value="0">--กรุณาเลือก--</option>
                    <option value="1">ฟอร์ม 1</option>
                    <option value="2">ฟอร์ม 2</option>
                </select>
            </div>

            <div id="form1" style="display:none;" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-all duration-300">
                <form id="myForm" class="space-y-4">
                    <p class="font-medium text-gray-800 mb-2">เรียน ผอ. วท.เลย</p>
                    
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="ทราบ" id="action1">
                            <label class="text-sm text-gray-700 cursor-pointer" for="action1">เพื่อโปรดทราบ</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="พิจารณา" id="action2">
                            <label class="text-sm text-gray-700 cursor-pointer" for="action2">เพื่อโปรดพิจารณา</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="แจ้ง" id="action3">
                            <label class="text-sm text-gray-700 cursor-pointer" for="action3">เห็นควรแจ้ง</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="มอบ" id="action4">
                            <label class="text-sm text-gray-700 cursor-pointer" for="action4">เห็นควรมอบ</label>
                        </div>
                        <div class="flex items-center gap-2 pt-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" id="signCheckbox" name="action" value="ลงนาม" onclick="toggleSignature()">
                            <label class="text-sm font-bold text-gray-700 cursor-pointer" for="signCheckbox">ลงนาม</label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2 mt-3">ความเห็น</label>
                        <textarea class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" name="comments" rows="3"></textarea>
                    </div>

                    <div>
                        <label for="department" class="block text-sm font-medium text-gray-700 mb-2">เสนอรองผู้อำนวยการฝ่าย</label>
                        <select id="department" name="dep-id" class="form-select w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" required>
                            <option value="" disabled selected>เลือกฝ่าย</option>
                            <?php
                            $inst_id = $_SESSION['inst_id'];
                            $dep_query = $conn->prepare("SELECT dep_id, dep_name FROM department WHERE inst_id = ?");
                            $dep_query->bind_param("i", $inst_id);
                            $dep_query->execute();
                            $departments = $dep_query->get_result()->fetch_all(MYSQLI_ASSOC);
                            $dep_query->close();
                            foreach ($departments as $department): ?>
                                <option value="<?= $department['dep_id'] ?>"><?= $department['dep_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex gap-2 pt-2">
                        <button type="button" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 rounded-lg transition-colors shadow-sm" onclick="submitForm()">แสดงตัวอย่าง</button>
                        <button type="button" onclick="location.reload();" class="flex-1 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium py-2 rounded-lg transition-colors shadow-sm">เริ่มใหม่</button>
                    </div>
                </form>
            </div>

            <div id="form2" style="display:none;" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-all duration-300">
                <form id="myForm2" class="space-y-4">
                    <p class="font-medium text-gray-800 mb-3 border-b pb-2">ตราปั้ม ทาน พิมพ์ คำสั่ง</p>
                    
                    <div>
                        <label for="department2" class="block text-sm font-medium text-gray-700 mb-2">เสนอรองผู้อำนวยการฝ่าย</label>
                        <select id="department2" name="dep-id" class="form-select w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" required>
                            <option value="" disabled selected>เลือกฝ่าย</option>
                            <?php
                            $inst_id = $_SESSION['inst_id'];
                            $dep_query = $conn->prepare("SELECT dep_id, dep_name FROM department WHERE inst_id = ?");
                            $dep_query->bind_param("i", $inst_id);
                            $dep_query->execute();
                            $departments = $dep_query->get_result()->fetch_all(MYSQLI_ASSOC);
                            $dep_query->close();
                            foreach ($departments as $department):
                                echo '<option value="' . $department['dep_id'] . '" data-dep-name="' . htmlspecialchars($department['dep_name']) . '">' . $department['dep_name'] . '</option>';
                            endforeach;
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="deputyDirector" class="block text-sm font-medium text-gray-700 mb-1">รอง ผอ</label>
                        <input type="text" class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" id="deputyDirector" placeholder="กรอกข้อมูลรอง ผอ">
                    </div>
                    <div>
                        <label for="supervisor" class="block text-sm font-medium text-gray-700 mb-1">ทาน</label>
                        <input type="text" class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" id="supervisor" value="<?php echo $_SESSION['username']," (",$_SESSION['fullname'],")";?>" placeholder="กรอกข้อมูลทาน">
                    </div>
                    <div>
                        <label for="headOfDepartment" class="block text-sm font-medium text-gray-700 mb-1">หัวหน้างาน</label>
                        <input type="text" class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" id="headOfDepartment" placeholder="กรอกข้อมูลหัวหน้างาน">
                    </div>
                    <div>
                        <label for="typist" class="block text-sm font-medium text-gray-700 mb-1">พิมพ์</label>
                        <input type="text" class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" id="typist" placeholder="กรอกข้อมูลพิมพ์">
                    </div>
                    
                    <div class="flex gap-2 pt-2">
                        <button type="button" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 rounded-lg transition-colors shadow-sm" onclick="submitForm2()">แสดงตัวอย่าง</button>
                        <button type="button" onclick="location.reload();" class="flex-1 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium py-2 rounded-lg transition-colors shadow-sm">เริ่มใหม่</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <form id="text-form" method="post" action="sarabun_generate_2.php" class="space-y-4">
                    <div>  
                        <label for="input-text" class="block text-sm font-medium text-gray-700 mb-2">ตัวอย่างข้อความแสดงผล:</label>
                        <textarea id="input-text" name="input-text" rows="4" class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" required></textarea>
                    </div>
                    
                    <?php if ($signatureImage): ?>
                        <div>
                        <label for="input-sign" class="block text-sm font-medium text-gray-700 mb-2">ตัวอย่างลายเซ็นต์ลงนาม</label>
                            <img id="signatureImage" src="<?= $signatureImage ?>" alt="Signature" width="70" height="100%" style="display: none;" class="border p-1 bg-gray-50 rounded">
                        </div>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="x-pos" class="block text-sm font-medium text-gray-700 mb-1">X Position:</label>
                            <input type="text" id="x-pos" name="x-pos" class="form-control w-full bg-gray-50 border-gray-300 rounded-lg text-sm text-gray-500 cursor-not-allowed" readonly>
                        </div>
                        <div>
                            <label for="y-pos" class="block text-sm font-medium text-gray-700 mb-1">Y Position:</label>
                            <input type="text" id="y-pos" name="y-pos" class="form-control w-full bg-gray-50 border-gray-300 rounded-lg text-sm text-gray-500 cursor-not-allowed" readonly>
                        </div>
                    </div>

                    <input type="hidden" id="page-num" name="page-num" value="<?= $page_num ?>">
                    <input type="hidden" name="doc_id" value="<?= $doc_id ?>">
                    <input type="hidden" name="file_id" value="<?= $file_id ?>">
                    <input type="hidden" id="hidden-dep-id" name="dep-id" value="">
                    <input type="hidden" id="sign-display" name="sign_display" value="False">
                    
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 rounded-lg transition-colors flex justify-center items-center gap-2 shadow-sm mt-4">
                        <i class="bi bi-save"></i> บันทึกข้อมูล
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
                                <?php if (!empty($detail['sign_pic'])): ?>
                                    <img src="<?= htmlspecialchars($detail['sign_pic']) ?>" alt="" width="70" height="auto">
                                <?php endif; ?>
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
                        <div id="preview-sign"></div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>
<!-- ๋JS Zone ๋JS Zone ๋JS Zone --><!-- ๋JS Zone ๋JS Zone ๋JS Zone -->

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
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('text-form').addEventListener('submit', function(event) {
                const xPos = document.getElementById('x-pos').value;
                const yPos = document.getElementById('y-pos').value;

                // Check for empty fields
                if (xPos.trim() === '' || yPos.trim() === '') {
                    alert('กรุณาคลิกตำแหน่งบนหน้ากระดาษ');
                    event.preventDefault(); // Prevent form submission
                }
            });
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
        previewSign = document.getElementById('preview-sign'),

    document.getElementById('prev-page').addEventListener('click', function() {
        if (pageNum <= 1) return;
        pageNum--;
        window.location.href = `sarabun_signtxt.php?doc_id=<?= $doc_id ?>&file_id=<?= $file_id ?>&page=${pageNum}`;
    });

    document.getElementById('next-page').addEventListener('click', function() {
        if (pageNum >= pageCount) return;
        pageNum++;
        window.location.href = `sarabun_signtxt.php?doc_id=<?= $doc_id ?>&file_id=<?= $file_id ?>&page=${pageNum}`;
    });

canvas.addEventListener('click', function(event) {
    let rect = canvas.getBoundingClientRect();
    let x = event.clientX - rect.left;
    let y = event.clientY - rect.top;

    document.getElementById('x-pos').value = x;
    document.getElementById('y-pos').value = y;

    let inputText = document.getElementById('input-text').value;
    let signatureCheckbox = document.getElementById('signCheckbox');

    // Clear previous preview
    previewText.style.display = 'none';
    previewSign.style.display = 'none';

    if (inputText) {
        previewText.style.left = `${x}px`;
        previewText.style.top = `${y}px`;
        previewText.textContent = inputText;
        previewText.style.display = 'block';

        // Calculate the height of the text preview
        let textHeight = previewText.offsetHeight;

        if (signatureCheckbox.checked) {
            previewSign.style.left = `${x}px`;
            // Adjust the top position of the signature preview based on text height
            previewSign.style.top = `${y + textHeight}px`; // Add some margin (10px) between the text and signature
            previewSign.style.backgroundImage = `url('<?= $signatureImage ?>')`;
            previewSign.style.display = 'block';
        }
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

    function submitForm() {
    var departmentSelect = document.getElementById("department");
    var departmentValue = departmentSelect.options[departmentSelect.selectedIndex].value;
    // Set the value in the hidden field of the second form
    document.getElementById("hidden-dep-id").value = departmentValue;
    var form = document.getElementById('myForm');
    var checkboxes = form.querySelectorAll('input[type="checkbox"]');
    var comments = form.querySelector('textarea[name="comments"]').value;
    var departmentSelect = form.querySelector('select[name="dep-id"]'); // Get the department select element
    var result = 'เรียน ผอ. วท.เลย\n';

    // Check if a department is selected
    if (departmentSelect.value === "") {
        alert("กรุณาเลือกฝ่าย");
        return; // Prevent the form from being submitted if no department is selected
    }

    // ตรวจสอบ checkbox ที่ถูกเลือก
    var action1 = 'เพื่อโปรด ';
    var action2 = 'เห็นควร ';
    var subResult1 = [];
    var subResult2 = [];

    checkboxes.forEach(function(checkbox) {
        if (checkbox.checked && checkbox.value !== "ลงนาม") {
            if (checkbox.value === 'ทราบ' || checkbox.value === 'พิจารณา') {
                subResult1.push('' + checkbox.value);
            } else if (checkbox.value === 'แจ้ง' || checkbox.value === 'มอบ') {
                subResult2.push('' + checkbox.value);
            }
        }
    });

    if (subResult1.length > 0) {
        result += action1 + subResult1.join(' ') + '\n';
    }
    if (subResult2.length > 0) {
        result += action2 + subResult2.join(' ') + '\n';
    }

    result += '' + comments + '';

    var currentDate = new Date();
    var options = { year: 'numeric', month: 'long', day: 'numeric' };
    var formattedDate = currentDate.toLocaleDateString('th-TH', options);

    document.getElementById('input-text').value = result + '\nวันที่ ' + formattedDate + '\n';

    // Display or hide the signature image on the canvas based on the checkbox
    var signatureCheckbox = document.getElementById('signCheckbox');
    var signatureImage = document.getElementById('signatureImage');
    if (signatureCheckbox.checked) {
        signatureImage.style.display = 'block';
    } else {
        signatureImage.style.display = 'none';
    }
}

</script>


<script>

function submitForm2() {
    var departmentSelect = document.getElementById("department2");
    var departmentValue = departmentSelect.options[departmentSelect.selectedIndex].value;
    // Set the value in the hidden field of the second form
    document.getElementById("hidden-dep-id").value = departmentValue;
    var form = document.getElementById('myForm2');
    var textArea = document.getElementById('input-text');
    var departmentSelect = form.querySelector('select[name="dep-id"]'); // Get the department select element
    var departmentName = departmentSelect.options[departmentSelect.selectedIndex].getAttribute('data-dep-name'); // Get the department name

    var result = 'เสนอรอง ผอ. : ' + departmentName + '\n';
    if (departmentSelect.value === "") {
        alert("กรุณาเลือกฝ่าย");
        return; // Prevent the form from being submitted if no department is selected
    }
    // เก็บข้อมูลจาก input และ select พร้อมกับจัดรูปแบบข้อความ
    var inputs = form.querySelectorAll('input[type="text"], select');
    inputs.forEach(function(input) {
        var label = input.previousElementSibling.innerText; // Get the label text
        if (input.tagName.toLowerCase() !== 'select') { // Skip the department select element
            result += label + ': ' + input.value + '\n';
        }
    });

    // เพิ่มข้อความจาก textarea ถ้ามี
    var textAreaInput = form.querySelector('textarea');
    if (textAreaInput) {
        result += 'Comments: ' + textAreaInput.value + '\n';
    }

    // ตั้งค่าผลลัพธ์ใน textarea ของ form อื่น
    textArea.value = result;
    // อัพเดทวันที่
    var currentDate = new Date();
    var options = { year: 'numeric', month: 'long', day: 'numeric' };
    var formattedDate = currentDate.toLocaleDateString('th-TH', options);
    textArea.value += 'วันที่ ' + formattedDate;
}

// ตรวจสอบการคลิกของปุ่มส่งฟอร์ม
document.getElementById('submitBtn').addEventListener('click', function(event) {
    event.preventDefault();  // ป้องกันไม่ให้ฟอร์มถูกส่งไปยัง server เมื่อกดปุ่ม
    submitForm2();
});


function toggleSignature() {
    var signatureCheckbox = document.getElementById('signCheckbox');
    var hiddenInput = document.getElementById('sign-display');
    if (signatureCheckbox.checked) {
        hiddenInput.value = "True";
        // Additional actions to display the signature image if needed
        document.getElementById('signatureImage').style.display = 'block';
    } else {
        hiddenInput.value = "False";
        // Additional actions to hide the signature image if needed
        document.getElementById('signatureImage').style.display = 'none';
    }
}


</script>
</body>
</html>
<?php
$content = ob_get_clean();
include 'base.php';
?>