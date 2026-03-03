import os
import re

def upgrade_modals(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            text = f.read()
    except Exception as e:
        print(f"Error reading {filepath}: {e}")
        return

    # Replace Draw Modal
    draw_modal_pattern = r'<!-- Draw Signature Modal -->\s*<div class="modal fade" id="drawSignModal".*?</div>\s*</div>\s*</div>'
    
    new_draw_modal = '''<!-- Draw Signature Modal (Tailwind) -->
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
</div>'''
    
    # We must make sure regex matches correctly. The problem is nested divs. We'll use a simpler replace or read lines.
    # Given regex can fail on nested divs. Let's find index.
    
    start_draw = text.find('<!-- Draw Signature Modal -->')
    if start_draw != -1:
        end_draw = text.find('<!-- ๋JS Zone', start_draw)
        if end_draw == -1:
            end_draw = text.find('<!-- Modal for Signature Gallery -->', start_draw)
        
        if end_draw != -1:
            # Safely extract the chunk
            text = text[:start_draw] + new_draw_modal + '\n\n' + text[end_draw:]


    # Replace Gallery Modal
    start_gal = text.find('<!-- Modal for Signature Gallery -->')
    if start_gal != -1:
        end_gal = text.find('</body>', start_gal)
        
        new_gal_modal = '''<!-- Gallery Signature Modal (Tailwind) -->
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
</div>\n'''
        text = text[:start_gal] + new_gal_modal + '\n</body>'

    # JS updates for the custom modals (removing bootstrap modal code)
    js_updates = {
        "var drawModal = new bootstrap.Modal(document.getElementById('drawSignModal'));": "document.getElementById('drawSignModal').classList.remove('hidden');",
        "drawModal.show();": "",
        "var drawModalEl = document.getElementById('drawSignModal');": "",
        "var drawModal = bootstrap.Modal.getInstance(drawModalEl);": "",
        "drawModal.hide();": "document.getElementById('drawSignModal').classList.add('hidden');",
        "var galModal = new bootstrap.Modal(document.getElementById('gallerySignModal'));": "document.getElementById('gallerySignModal').classList.remove('hidden');",
        "galModal.show();": "",
        "var galModalEl = document.getElementById('gallerySignModal');": "",
        "var galModal = bootstrap.Modal.getInstance(galModalEl);": "",
        "galModal.hide();": "document.getElementById('gallerySignModal').classList.add('hidden');",
    }
    
    for old, new_js in js_updates.items():
        text = text.replace(old, new_js)

    # Add closer functions safely
    close_funcs = '''function closeCustomSignModal() {
    document.getElementById('drawSignModal').classList.add('hidden');
}
function closeGalleryModal() {
    document.getElementById('gallerySignModal').classList.add('hidden');
}'''
    
    if "closeCustomSignModal" not in text:
        text = text.replace("function saveCustomSignature", close_funcs + "\n\nfunction saveCustomSignature")

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(text)
    print(f"Modal Update Complete for {filepath}")

upgrade_modals(r'c:\Users\Korarak\Desktop\edoc67\director_sign.php')
upgrade_modals(r'c:\Users\Korarak\Desktop\edoc67\codirector_sign.php')
