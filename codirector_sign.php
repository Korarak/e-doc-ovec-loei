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

// Fetch all user signatures for gallery selection
$user_id = $_SESSION['user_id'];
$gal_query = $conn->prepare("SELECT sig_id, sign_path FROM user_signatures WHERE user_id = ? ORDER BY is_primary DESC, created_at DESC");
$gal_query->bind_param("i", $user_id);
$gal_query->execute();
$user_gallery = $gal_query->get_result()->fetch_all(MYSQLI_ASSOC);
$gal_query->close();
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
            touch-action: none;
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
        #custom-signature-pad {
            border: 2px dashed #ccc;
            border-radius: 8px;
            background-color: #f8f9fa;
            touch-action: none;
            cursor: crosshair;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.5.207/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

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
            <i class="bi bi-pen text-brand-600"></i> เกษียณโดยรองผู้อำนวยการ
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
                    <option value="1">ตราปั้ม เสนอ ผอ</option>
                </select>
            </div>

            <div id="form1" style="display:none;" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-all duration-300">
                <form id="myForm" class="space-y-4">
                    <p class="font-medium text-gray-800 mb-2">เรียน ผอ. วท.เลย</p>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="ทราบ" id="action1">
                            <label class="text-sm text-gray-700 cursor-pointer pt-1" for="action1">เพื่อโปรดทราบ</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="พิจารณา" id="action2">
                            <label class="text-sm text-gray-700 cursor-pointer pt-1" for="action2">เพื่อโปรดพิจารณา</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="แจ้ง" id="action3">
                            <label class="text-sm text-gray-700 cursor-pointer pt-1" for="action3">เห็นควรแจ้ง</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="มอบ" id="action4">
                            <label class="text-sm text-gray-700 cursor-pointer pt-1" for="action4">เห็นควรมอบ</label>
                        </div>
                        
                        <div class="pt-2 border-t mt-3 border-gray-100">
                            <div class="flex items-center gap-2 mb-2">
                                <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" id="signCheckbox" name="action" value="ลงนาม" onclick="toggleSignature()">
                                <label class="text-sm font-bold text-gray-800 cursor-pointer pt-1" for="signCheckbox">ลงนามด้วยลายเซ็น</label>
                            </div>
                            
                            <div id="customSignContainer" style="display:none;" class="mt-3 border border-brand-100 rounded-xl p-4 bg-brand-50/30">
                                <p class="text-sm text-gray-700 mb-3 font-medium">ลายเซ็นที่เลือกใช้งาน:</p>
                                
                                <div class="flex items-center justify-between bg-white border border-gray-200 p-3 rounded-lg mb-4 shadow-sm relative overflow-hidden group">
                                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-brand-500"></div>
                                    <div id="currentSignaturePreview" class="h-14 w-40 bg-contain bg-left bg-no-repeat pl-4" style="background-image: url('<?= htmlspecialchars($signatureImage) ?>');"></div>
                                    <span id="customSignStatusText" class="text-xs text-brand-700 font-medium px-2 py-1 bg-brand-50 rounded border border-brand-100 flex items-center gap-1 shadow-sm">
                                        <i class="bi bi-star-fill text-brand-500"></i> ลายเซ็นหลัก
                                    </span>
                                </div>

                                <p class="text-xs text-gray-500 mb-2">เปลี่ยนลายเซ็นสำหรับเอกสารนี้ (ตัวเลือกเสริม):</p>
                                <div class="grid grid-cols-2 gap-3 text-center">
                                    <button type="button" class="col-span-1 py-2.5 px-2 bg-white border border-brand-300 text-brand-700 hover:bg-brand-50 hover:border-brand-400 text-sm font-medium rounded-lg transition-all shadow-sm flex flex-col items-center justify-center gap-1" onclick="openCustomSignModal()">
                                        <i class="bi bi-pencil-square text-xl"></i>
                                        <span>เซ็นสดใหม่อีกครั้ง</span>
                                    </button>
                                    <?php if (!empty($user_gallery)): ?>
                                    <button type="button" class="col-span-1 py-2.5 px-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400 text-sm font-medium rounded-lg transition-all shadow-sm flex flex-col items-center justify-center gap-1" onclick="openGalleryModal()">
                                        <i class="bi bi-images text-xl text-gray-600"></i>
                                        <span>เลือกจากภาพอื่น</span>
                                    </button>
                                    <?php else: ?>
                                    <div class="col-span-1 py-2.5 px-2 bg-gray-50 border border-gray-200 text-gray-400 text-sm font-medium rounded-lg flex flex-col items-center justify-center gap-1 opacity-75">
                                        <i class="bi bi-images text-xl opacity-50"></i>
                                        <span>ไม่มีลายเซ็นอื่น</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div id="cancelCustomBtnContainer" style="display: none;" class="mt-4 pt-4 border-t border-gray-200 text-center">
                                    <button type="button" class="text-sm text-red-500 hover:text-red-700 font-medium flex items-center justify-center mx-auto gap-2 transition-colors bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg" onclick="clearCustomSign()">
                                        <i class="bi bi-arrow-counterclockwise"></i> ยกเลิก และกลับไปใช้ลายเซ็นหลัก
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-gray-100">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">ความเห็น</label>
                            <button type="button" id="btn-dictate-1" class="btn btn-sm btn-outline-danger rounded-full px-3 shadow-sm flex items-center gap-1" title="พิมพ์ด้วยเสียง">
                                <i class="bi bi-mic-fill"></i> พูด
                            </button>
                        </div>
                        <textarea class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" id="comments-1" name="comments" rows="3"></textarea>
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
                    
                    <div style="display: none;">
                        <label for="department2" class="block text-sm font-medium text-gray-700 mb-2">เสนอผู้อำนวยการ</label>
                        <select id="department2" name="dep-id" class="form-select w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500">
                            <option value="1" data-dep-name="ผู้อำนวยการ" selected>ผู้อำนวยการ</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="deputyDirector" class="block text-sm font-medium text-gray-700 mb-1">รอง ผอ</label>
                        <input type="text" class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" id="deputyDirector" placeholder="กรอกข้อมูลรอง ผอ">
                    </div>
                    <div>
                        <label for="supervisor" class="block text-sm font-medium text-gray-700 mb-1">ทาน</label>
                        <input type="text" class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" id="supervisor" value="<?php echo $_SESSION['username']," (",$_SESSION['fullname'],")";?>"placeholder="กรอกข้อมูลทาน">
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
                <form id="text-form" method="post" action="sarabun_generate_3.php" class="space-y-4">
                    <div>  
                        <div class="flex justify-between items-center mb-2">
                            <label for="input-text" class="block text-sm font-medium text-gray-700 mb-0">ตัวอย่างข้อความแสดงผล:</label>
                            <button type="button" id="btn-dictate-main" class="btn btn-sm btn-outline-danger rounded-full px-3 shadow-sm flex items-center gap-1" title="พิมพ์ด้วยเสียง">
                                <i class="bi bi-mic-fill"></i> พูด
                            </button>
                        </div>
                        <textarea id="input-text" name="input-text" rows="4" class="form-control w-full border-gray-300 rounded-lg focus:ring-brand-500 focus:border-brand-500" required></textarea>
                    </div>
                    
                    <?php if ($signatureImage): ?>
                        <div class="mb-3">
                            <label for="input-sign" class="block text-sm font-medium text-gray-700 mb-2">ตัวอย่างลายเซ็นต์ลงนาม</label>
                            <img id="signatureImage" src="<?= htmlspecialchars($signatureImage) ?>" alt="Signature" width="70" height="auto" style="display: none;" class="border p-1 bg-gray-50 rounded">
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
                    <input type="hidden" name="signer_role" value="codirector">
                    <input type="hidden" id="hidden-dep-id" name="dep-id" value="">
                    <input type="hidden" id="sign-display" name="sign_display" value="False">
                    <input type="hidden" id="custom_sign_base64" name="custom_sign_base64" value="">
                    <input type="hidden" id="custom_sign_path" name="custom_sign_path" value="">

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
<!-- Draw Signature Modal (Tailwind) -->
<div id="drawSignModal" class="hidden fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center p-4 transition-opacity">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden transform scale-100" onclick="event.stopPropagation()">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <h5 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="bi bi-pencil-square text-brand-600"></i> วาดลายเซ็นสดใหม่
            </h5>
            <button onclick="closeCustomSignModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>
        
        <!-- Body -->
        <div class="p-6 bg-gray-50 text-center">
            <p class="text-sm text-gray-500 mb-4 font-medium">ใช้นิ้ว ปากกา หรือเมาส์ วาดลายเซ็นในพื้นที่ด้านล่าง</p>
            <div class="bg-white border-2 border-dashed border-gray-300 rounded-xl relative overflow-hidden shadow-sm" style="height: 220px; touch-action: none;">
                <canvas id="custom-signature-pad" class="absolute inset-0 w-full h-full cursor-crosshair"></canvas>
            </div>
            <div class="flex justify-end mt-3">
                <button type="button" onclick="customSignaturePad.clear()" class="text-red-500 hover:text-red-700 text-sm font-medium flex items-center transition-colors">
                    <i class="bi bi-eraser mr-1"></i> ล้างกระดาน
                </button>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-100 bg-white flex justify-end gap-3">
            <button type="button" onclick="closeCustomSignModal()" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">
                ยกเลิก
            </button>
            <button type="button" onclick="saveCustomSignature()" class="px-5 py-2.5 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-all shadow-sm flex items-center">
                <i class="bi bi-check2 mr-2"></i> ใช้ลายเซ็นนี้
            </button>
        </div>
    </div>
</div>

<!-- ๋JS Zone ๋JS Zone ๋JS Zone -->

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
        previewDate = document.getElementById('preview-date'),
        previewSign = document.getElementById('preview-sign');

    document.getElementById('prev-page').addEventListener('click', function() {
        if (pageNum <= 1) return;
        pageNum--;
        window.location.href = `codirector_sign.php?doc_id=<?= $doc_id ?>&file_id=<?= $file_id ?>&page=${pageNum}`;
    });

    document.getElementById('next-page').addEventListener('click', function() {
        if (pageNum >= pageCount) return;
        pageNum++;
        window.location.href = `codirector_sign.php?doc_id=<?= $doc_id ?>&file_id=<?= $file_id ?>&page=${pageNum}`;
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
            
            // Check if using custom drawing
            let customBase64 = document.getElementById('custom_sign_base64').value;
            if (customBase64) {
                previewSign.style.backgroundImage = `url('${customBase64}')`;
            } else {
                previewSign.style.backgroundImage = `url('<?= htmlspecialchars($signatureImage) ?>')`;
            }
            
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
    // Set the value in the hidden field of the second form
    var form = document.getElementById('myForm');
    var checkboxes = form.querySelectorAll('input[type="checkbox"]');
    var comments = form.querySelector('textarea[name="comments"]').value || document.getElementById('comments-1').value;
    var result = 'เรียน ผอ. วท.เลย\n';

    // Check if a department is selected
/*     if (departmentSelect.value === "") {
        alert("กรุณาเลือกฝ่าย");
        return; // Prevent the form from being submitted if no department is selected
    } */

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

    var result = '';
    
    // เก็บข้อมูลจาก input และ select พร้อมกับจัดรูปแบบข้อความ
    var inputs = form.querySelectorAll('input[type="text"]');
    inputs.forEach(function(input) {
        var label = input.previousElementSibling.innerText; // ดึงข้อความจาก label
        result += label + ': ' + input.value + '\n';
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


function toggleSignature() {
    var signatureCheckbox = document.getElementById('signCheckbox');
    var hiddenInput = document.getElementById('sign-display');
    var customSignContainer = document.getElementById('customSignContainer');
    
    if (signatureCheckbox.checked) {
        hiddenInput.value = "True";
        customSignContainer.style.display = 'block';
        
        let customBase64 = document.getElementById('custom_sign_base64').value;
        if(customBase64) {
            document.getElementById('signatureImage').src = customBase64;
        } else {
            document.getElementById('signatureImage').src = '<?= htmlspecialchars($signatureImage) ?>';
        }
        document.getElementById('signatureImage').style.display = 'block';
    } else {
        hiddenInput.value = "False";
        customSignContainer.style.display = 'none';
        document.getElementById('signatureImage').style.display = 'none';
    }
}

// === Custom Signature Pad Logic ===
var customSignaturePad;
document.addEventListener("DOMContentLoaded", function() {
    var canvas = document.getElementById('custom-signature-pad');
    customSignaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgba(255, 255, 255, 0)',
        penColor: 'rgb(0, 0, 0)',
        minWidth: 1.5,
        maxWidth: 3
    });
    
    // Voice Dictation Logic
    setupVoiceDictation('btn-dictate-1', 'comments-1');
    setupVoiceDictation('btn-dictate-main', 'input-text');
});

function openCustomSignModal() {
    document.getElementById('drawSignModal').classList.remove('hidden');
    
    setTimeout(() => {
        var canvas = document.getElementById('custom-signature-pad');
        // resize to fit modal
        var ratio =  Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        customSignaturePad.clear();
    }, 200);
}

function saveCustomSignature() {
    if (customSignaturePad.isEmpty()) {
        alert("กรุณาวาดลายเซ็น");
        return;
    }
    
    var dataURL = customSignaturePad.toDataURL('image/png');
    document.getElementById('custom_sign_base64').value = dataURL;
    document.getElementById('custom_sign_path').value = ''; // clear path if any
    
    document.getElementById('customSignStatusText').innerHTML = '<i class="bi bi-pencil-fill"></i> ลายเซ็นสดใหม่';
    let cancelBtn = document.getElementById('cancelCustomBtnContainer');
    if (cancelBtn) cancelBtn.style.display = 'block';
    
    let preview = document.getElementById('currentSignaturePreview');
    if (preview) preview.style.backgroundImage = `url('${dataURL}')`;
    
    document.getElementById('signatureImage').src = dataURL; // Update preview img
    document.getElementById('signatureImage').style.display = 'block';
    
    
    
    document.getElementById('drawSignModal').classList.add('hidden');
}

function clearCustomSign() {
    document.getElementById('custom_sign_base64').value = '';
    document.getElementById('custom_sign_path').value = '';
    
    document.getElementById('customSignStatusText').innerHTML = '<i class="bi bi-star-fill text-brand-500"></i> ลายเซ็นหลัก';
    let cancelBtn = document.getElementById('cancelCustomBtnContainer');
    if (cancelBtn) cancelBtn.style.display = 'none';
    
    let preview = document.getElementById('currentSignaturePreview');
    if (preview) preview.style.backgroundImage = `url('<?= htmlspecialchars($signatureImage) ?>')`;
    
    document.getElementById('signatureImage').src = '<?= htmlspecialchars($signatureImage) ?>'; // revert
    
    if (!document.getElementById('signCheckbox').checked) {
        document.getElementById('signatureImage').style.display = 'none';
    }
}

function openGalleryModal() {
    document.getElementById('gallerySignModal').classList.remove('hidden');
    
}

function selectGallerySignature(path) {
    document.getElementById('custom_sign_path').value = path;
    document.getElementById('custom_sign_base64').value = ''; // clear b64 if any
    
    document.getElementById('customSignStatusText').innerHTML = '<i class="bi bi-check-circle-fill"></i> เลือกจากภาพ';
    let cancelBtn = document.getElementById('cancelCustomBtnContainer');
    if (cancelBtn) cancelBtn.style.display = 'block';
    
    let preview = document.getElementById('currentSignaturePreview');
    if (preview) preview.style.backgroundImage = `url('${path}')`;
    
    document.getElementById('signatureImage').src = path; // Update preview img
    document.getElementById('signatureImage').style.display = 'block';
    
    
    
    document.getElementById('gallerySignModal').classList.add('hidden');
}

// === Voice Dictation Logic ===
function setupVoiceDictation(btnId, targetInputId) {
    const btn = document.getElementById(btnId);
    const targetInput = document.getElementById(targetInputId);
    
    if (!btn || !targetInput) return;
    
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        btn.style.display = 'none'; // Hide if not supported
        return;
    }

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognition = new SpeechRecognition();
    
    recognition.lang = 'th-TH'; // Thai language
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    
    let isRecording = false;
    let originalHtml = btn.innerHTML;

    btn.addEventListener('click', () => {
        if (isRecording) {
            recognition.stop();
        } else {
            recognition.start();
        }
    });

    recognition.onstart = function() {
        isRecording = true;
        btn.classList.remove('btn-outline-danger');
        btn.classList.add('btn-danger');
        btn.innerHTML = '<i class="bi bi-mic-fill animate-pulse"></i> กำลังฟัง...';
    };

    recognition.onresult = function(event) {
        const transcript = event.results[0][0].transcript;
        if(targetInput.value.length > 0 && !targetInput.value.endsWith(' ') && !targetInput.value.endsWith('\n')) {
             targetInput.value += ' ' + transcript;
        } else {
             targetInput.value += transcript;
        }
    };

    recognition.onerror = function(event) {
        console.error('Speech recognition error', event.error);
        alert('เกิดข้อผิดพลาดในการรับเสียง: ' + event.error);
    };

    recognition.onend = function() {
        isRecording = false;
        btn.classList.remove('btn-danger');
        btn.classList.add('btn-outline-danger');
        btn.innerHTML = originalHtml;
    };
}

</script>

<!-- Gallery Signature Modal (Tailwind) -->
<div id="gallerySignModal" class="hidden fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center p-4 transition-opacity">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]" onclick="event.stopPropagation()">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50/50 shrink-0">
            <h5 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="bi bi-images text-brand-600"></i> เลือกลายเซ็นจากแกลเลอรี
            </h5>
            <button onclick="closeGalleryModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>
        
        <!-- Body (Scrollable) -->
        <div class="p-6 bg-gray-50 overflow-y-auto grow">
            <p class="text-sm text-gray-500 mb-4 font-medium">คลิกเลือกลายเซ็นที่คุณต้องการใช้งานสำหรับเอกสารฉบับนี้</p>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php if (!empty($user_gallery)): ?>
                    <?php foreach ($user_gallery as $sig): ?>
                        <div class="group bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md hover:border-brand-400 transition-all cursor-pointer relative" onclick="selectGallerySignature('<?= htmlspecialchars($sig['sign_path']) ?>')">
                            <!-- Selected indicator (hidden by default, shown on hover for hint) -->
                            <div class="absolute inset-0 bg-brand-500/0 group-hover:bg-brand-500/5 transition-colors z-10 flex items-center justify-center flex-col gap-2 opacity-0 group-hover:opacity-100">
                                <span class="bg-white/90 text-brand-700 text-xs font-bold px-3 py-1.5 rounded-full shadow-sm">
                                    <i class="bi bi-check2-circle"></i> เลือกภาพนี้
                                </span>
                            </div>
                            
                            <div class="h-32 p-3 flex items-center justify-center bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPgo8cmVjdCB3aWR0aD0iOCIgaGVpZ2h0PSI4IiBmaWxsPSIjZmZmIj48L3JlY3Q+CjxyZWN0IHdpZHRoPSI0IiBoZWlnaHQ9IjQiIGZpbGw9IiNmM2YzZjMiPjwvcmVjdD4KPHJlY3QgeD0iNCIgeT0iNCIgd2lkdGg9IjQiIGhlaWdodD0iNCIgZmlsbD0iI2YzZjNmMyI+PC9yZWN0Pgo8L3N2Zz4=')]">
                                <?php if (file_exists($sig['sign_path'])): ?>
                                    <img src="<?= htmlspecialchars($sig['sign_path']) ?>" class="max-h-full max-w-full object-contain drop-shadow-sm">
                                <?php else: ?>
                                    <div class="text-center text-gray-400">
                                        <i class="bi bi-file-earmark-x text-2xl mb-1 block"></i>
                                        <span class="text-xs">ไม่พบไฟล์</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full py-12 text-center bg-white rounded-xl border border-dashed border-gray-300">
                        <i class="bi bi-images text-4xl text-gray-300 mb-3 block"></i>
                        <p class="text-gray-500 font-medium text-sm">คุณยังไม่มีลายเซ็นในแกลเลอรี</p>
                        <p class="text-gray-400 text-xs mt-1">กรุณาเพิ่มลายเซ็นในเมนู "ตั้งค่าลายเซ็น"</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-100 bg-white shrink-0">
            <button type="button" onclick="closeGalleryModal()" class="w-full sm:w-auto px-6 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">
                ปิดหน้าต่าง
            </button>
        </div>
    </div>
</div>

</body>
</html>
<?php
$content = ob_get_clean();
include 'base.php';
?>