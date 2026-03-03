import os
import re

def fix_signing_ux(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            text = f.read()
    except Exception as e:
        print(f"Error reading {filepath}: {e}")
        return

    # Replace the container
    container_pattern = r'<div id="customSignContainer" style="display:none;" class="mt-3 pl-4 border-l-2 border-brand-200">.*?</div>\s*</div>\s*</div>'
    
    new_container = '''<div id="customSignContainer" style="display:none;" class="mt-3 border border-brand-100 rounded-xl p-4 bg-brand-50/30">
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
                        </div>'''
    
    text = re.sub(container_pattern, new_container, text, flags=re.DOTALL)

    # JS saveCustomSignature
    save_js_pattern = r'function saveCustomSignature\(\) \{.*?drawModal\.hide\(\);\n\}'
    new_save_js = '''function saveCustomSignature() {
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
    
    var drawModalEl = document.getElementById('drawSignModal');
    var drawModal = bootstrap.Modal.getInstance(drawModalEl);
    drawModal.hide();
}'''
    text = re.sub(save_js_pattern, new_save_js, text, flags=re.DOTALL)

    # JS clearCustomSign
    clear_js_pattern = r'function clearCustomSign\(\) \{.*?\}'
    new_clear_js = '''function clearCustomSign() {
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
}'''
    # We must only replace the first occurrence or specific one, actually there's only one.
    text = re.sub(clear_js_pattern, new_clear_js, text, flags=re.DOTALL)

    # JS selectGallerySignature
    gal_js_pattern = r'function selectGallerySignature\(path\) \{.*?galModal\.hide\(\);\n\}'
    new_gal_js = '''function selectGallerySignature(path) {
    document.getElementById('custom_sign_path').value = path;
    document.getElementById('custom_sign_base64').value = ''; // clear b64 if any
    
    document.getElementById('customSignStatusText').innerHTML = '<i class="bi bi-check-circle-fill"></i> เลือกจากภาพ';
    let cancelBtn = document.getElementById('cancelCustomBtnContainer');
    if (cancelBtn) cancelBtn.style.display = 'block';
    
    let preview = document.getElementById('currentSignaturePreview');
    if (preview) preview.style.backgroundImage = `url('${path}')`;
    
    document.getElementById('signatureImage').src = path; // Update preview img
    document.getElementById('signatureImage').style.display = 'block';
    
    var galModalEl = document.getElementById('gallerySignModal');
    var galModal = bootstrap.Modal.getInstance(galModalEl);
    galModal.hide();
}'''
    text = re.sub(gal_js_pattern, new_gal_js, text, flags=re.DOTALL)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(text)
    print(f"Update Complete for {filepath}")

fix_signing_ux(r'c:\Users\Korarak\Desktop\edoc67\director_sign.php')
fix_signing_ux(r'c:\Users\Korarak\Desktop\edoc67\codirector_sign.php')
