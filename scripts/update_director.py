import os

def update_director_sign():
    file_path = r'c:\Users\Korarak\Desktop\edoc67\director_sign.php'
    with open(file_path, 'r', encoding='utf-8') as f:
        text = f.read()

    start_marker = '<div class="container mt-2 rounded shadow p-3">'
    end_marker = '<!-- Draw Signature Modal -->'

    new_ui = """<div class="py-4">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="bi bi-pen text-brand-600"></i> เกษียณโดยผู้อำนวยการ
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
                    <option value="1">ผู้อำนวยการ</option>
                </select>
            </div>

            <div id="form1" style="display:none;" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-all duration-300">
                <form id="myForm" class="space-y-4">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="ทราบ" id="action1">
                            <label class="text-sm text-gray-700 cursor-pointer pt-1" for="action1">ทราบ</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="แจ้ง" id="action3">
                            <label class="text-sm text-gray-700 cursor-pointer pt-1" for="action3">แจ้ง</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" name="action" value="มอบ" id="action4">
                            <label class="text-sm text-gray-700 cursor-pointer pt-1" for="action4">มอบ</label>
                        </div>
                        
                        <div class="pt-2 border-t mt-3 border-gray-100">
                            <div class="flex items-center gap-2 mb-2">
                                <input class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500" type="checkbox" id="signCheckbox" name="action" value="ลงนาม" onclick="toggleSignature()">
                                <label class="text-sm font-bold text-gray-800 cursor-pointer pt-1" for="signCheckbox">ลงนามด้วยลายเซ็น</label>
                            </div>
                            
                            <div id="customSignContainer" style="display:none;" class="mt-3 pl-4 border-l-2 border-brand-200">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary shadow-sm rounded-lg flex items-center gap-1" onclick="openCustomSignModal()">
                                        <i class="bi bi-pencil"></i> เขียนลายเซ็นด้วยตนเอง
                                    </button>
                                    <?php if (!empty($user_gallery)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm rounded-lg flex items-center gap-1" onclick="openGalleryModal()">
                                        <i class="bi bi-images"></i> เลือกลายเซ็นอื่น
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div id="customSignPreviewContainer" class="mt-2 p-2 bg-green-50 rounded-lg border border-green-100 flex items-center justify-between" style="display:none;">
                                    <span id="customSignStatusText" class="text-green-700 text-sm font-medium"><i class="bi bi-check-circle mr-1"></i> ใช้ลายเซ็นที่อัปเดตใหม่</span>
                                    <button type="button" class="text-red-500 hover:text-red-700 text-sm font-medium px-2 py-1" onclick="clearCustomSign()"><i class="bi bi-x-lg mr-1"></i>ยกเลิก</button>
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
                            foreach ($departments as $department): ?>
                                <option value="<?= $department['dep_id'] ?>"><?= $department['dep_name'] ?></option>
                            <?php endforeach; ?>
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
                            <div class="annotation" style="left:<?= $detail['x_pos'] ?>px; top: <?= $detail['y_pos'] ?>px;">
                                <?= nl2br(htmlspecialchars($detail['sign_txt'])) ?><br>
                                <?php if (!empty($detail['sign_pic'])): ?>
                                    <img src="<?= htmlspecialchars($detail['sign_pic']) ?>" alt="" width="70" height="auto">
                                <?php endif; ?>
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
"""

    i1 = text.find(start_marker)
    i2 = text.find(end_marker)
    if i1 != -1 and i2 != -1:
        text = text[:i1] + new_ui + text[i2:]
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(text)
        print("Success for director_sign")
    else:
        print("Markers not found for director_sign")

if __name__ == '__main__':
    update_director_sign()
    print("Done")
