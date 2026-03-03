<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'edoc-db.php';

$user_id = $_SESSION['user_id'];

// Fetch all signatures for this user
$stmt = $conn->prepare("SELECT sig_id, sign_path, is_primary FROM user_signatures WHERE user_id = ? ORDER BY is_primary DESC, created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$signatures = [];
while ($row = $result->fetch_assoc()) {
    $signatures[] = $row;
}
$stmt->close();
?>

<div class="py-2">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="bi bi-images text-brand-600"></i> แกลเลอรีลายเซ็นของฉัน
        </h2>
        <button onclick="openAddSignatureModal()" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center gap-2 transition-all shadow-sm">
            <i class="bi bi-plus-lg"></i> เพิ่มลายเซ็นใหม่
        </button>
    </div>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php if (empty($signatures)): ?>
            <div class="col-span-full text-center py-12 bg-white rounded-xl border border-gray-100 shadow-sm">
                <i class="bi bi-pen text-4xl text-gray-300 mb-3 block"></i>
                <h3 class="text-lg font-medium text-gray-600">คุณยังไม่มีลายเซ็นในระบบ</h3>
                <p class="text-gray-400 mt-1">คลิกที่ปุ่มเพิ่มลายเซ็นใหม่เพื่อวาดหรืออัปโหลดลายเซ็นของคุณ</p>
                <button onclick="openAddSignatureModal()" class="mt-4 px-4 py-2 bg-brand-50 text-brand-700 font-medium rounded-lg hover:bg-brand-100 transition-colors">
                    <i class="bi bi-plus"></i> เพิ่มลายเซ็น
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($signatures as $sig): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md transition-all relative flex flex-col">
                    <?php if ($sig['is_primary'] == 1): ?>
                        <div class="absolute top-2 left-2 bg-brand-500 text-white text-[10px] font-bold px-2 py-1 rounded shadow-sm z-10 flex items-center gap-1">
                            <i class="bi bi-star-fill"></i> ลายเซ็นหลัก
                        </div>
                    <?php endif; ?>
                    
                    <div class="h-40 bg-gray-50/50 p-4 border-b border-gray-100 flex items-center justify-center relative">
                        <?php if (file_exists($sig['sign_path'])): ?>
                            <img src="<?= htmlspecialchars($sig['sign_path']) ?>" class="max-h-full max-w-full object-contain filter group-hover:brightness-95 transition-all">
                        <?php else: ?>
                            <span class="text-gray-400 text-sm">ไม่พบไฟล์ภาพ</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-3 bg-white flex justify-between items-center gap-2 mt-auto">
                        <?php if ($sig['is_primary'] == 0): ?>
                            <form action="save_signature.php" method="POST" class="m-0 flex-1">
                                <input type="hidden" name="action" value="set_primary">
                                <input type="hidden" name="sig_id" value="<?= $sig['sig_id'] ?>">
                                <button type="submit" class="w-full text-xs py-1.5 px-2 bg-white border border-brand-300 text-brand-700 font-medium rounded hover:bg-brand-50 transition-colors text-center">
                                    <i class="bi bi-star"></i> ตั้งเป็นหลัก
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="flex-1"></div>
                        <?php endif; ?>
                        
                        <form action="save_signature.php" method="POST" class="m-0" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบลายเซ็นนี้?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="sig_id" value="<?= $sig['sig_id'] ?>">
                            <button type="submit" class="text-xs py-1.5 px-2.5 bg-red-50 text-red-600 hover:bg-red-100 font-medium rounded transition-colors" title="ลบ">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Signature Modal -->
<div id="addSignatureModal" class="modal-overlay hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden transform transition-all" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <h5 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="bi bi-pen-fill text-brand-600"></i> เพิ่มลายเซ็นใหม่
            </h5>
            <button onclick="closeAddSignatureModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <!-- Tabs -->
        <div class="flex border-b border-gray-200">
            <button onclick="switchTab('draw')" id="tab-draw" class="flex-1 py-3 text-sm font-medium border-b-2 text-brand-600 border-brand-600 bg-white">
                <i class="bi bi-pencil-square mr-1"></i> วาดลายเซ็นสด
            </button>
            <button onclick="switchTab('upload')" id="tab-upload" class="flex-1 py-3 text-sm font-medium border-b-2 text-gray-500 border-transparent bg-gray-50 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                <i class="bi bi-upload mr-1"></i> อัปโหลดรูปภาพ
            </button>
        </div>

        <!-- Draw Panel -->
        <div id="panel-draw" class="p-6">
            <div class="border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 relative" style="height: 200px; touch-action: none;">
                <canvas id="signature-pad" class="absolute inset-0 w-full h-full cursor-crosshair rounded-xl"></canvas>
            </div>
            <div class="flex justify-between items-center mt-4">
                <p class="text-xs text-gray-500">ใช้นิ้ว ปากกา หรือเมาส์ วาดลายเซ็น</p>
                <button type="button" onclick="clearSignaturePad()" class="text-red-500 hover:text-red-700 text-sm font-medium flex items-center transition-colors">
                    <i class="bi bi-eraser mr-1"></i> ล้างกระดาน
                </button>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button onclick="closeAddSignatureModal()" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">ยกเลิก</button>
                <button id="save-draw-btn" class="px-5 py-2.5 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-all shadow-sm flex items-center">
                    <i class="bi bi-save mr-2"></i> บันทึกลายเซ็น
                </button>
            </div>
        </div>

        <!-- Upload Panel -->
        <div id="panel-upload" class="p-6 hidden bg-gray-50">
            <form action="save_signature.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="bg-white border text-center border-gray-200 rounded-xl p-6 shadow-sm">
                    <i class="bi bi-cloud-arrow-up text-4xl text-brand-500 mb-3 block"></i>
                    <label class="block text-sm font-medium text-gray-700 mb-2">เลือกรูปภาพลายเซ็น (PNG, JPG)</label>
                    <input type="file" name="sign_file" accept="image/png, image/jpeg" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 mx-auto max-w-sm border border-gray-200">
                    <p class="text-xs text-gray-400 mt-3">แนะนำ: ใช้รูปที่มีพื้นหลังโปร่งใส (.png)</p>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeAddSignatureModal()" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors shadow-sm">ยกเลิก</button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-all shadow-sm flex items-center">
                        <i class="bi bi-upload mr-2"></i> อัปโหลดและบันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
    let signaturePad;

    function switchTab(tab) {
        if (tab === 'draw') {
            document.getElementById('tab-draw').className = "flex-1 py-3 text-sm font-medium border-b-2 text-brand-600 border-brand-600 bg-white";
            document.getElementById('tab-upload').className = "flex-1 py-3 text-sm font-medium border-b-2 text-gray-500 border-transparent bg-gray-50 hover:text-gray-700 hover:bg-gray-100 transition-colors";
            document.getElementById('panel-draw').classList.remove('hidden');
            document.getElementById('panel-upload').classList.add('hidden');
            setTimeout(resizeCanvas, 50);
        } else {
            document.getElementById('tab-upload').className = "flex-1 py-3 text-sm font-medium border-b-2 text-brand-600 border-brand-600 bg-white";
            document.getElementById('tab-draw').className = "flex-1 py-3 text-sm font-medium border-b-2 text-gray-500 border-transparent bg-gray-50 hover:text-gray-700 hover:bg-gray-100 transition-colors";
            document.getElementById('panel-upload').classList.remove('hidden');
            document.getElementById('panel-draw').classList.add('hidden');
        }
    }

    function openAddSignatureModal() {
        document.getElementById('addSignatureModal').classList.remove('hidden');
        if(!signaturePad) {
            const canvas = document.getElementById('signature-pad');
            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)',
                minWidth: 1.5,
                maxWidth: 3.5
            });
        }
        setTimeout(resizeCanvas, 100);
    }

    function closeAddSignatureModal() {
        document.getElementById('addSignatureModal').classList.add('hidden');
        if (signaturePad) signaturePad.clear();
    }

    function resizeCanvas() {
        if(!signaturePad) return;
        const canvas = document.getElementById('signature-pad');
        const ratio =  Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        signaturePad.clear();
    }
    window.addEventListener("resize", resizeCanvas);

    function clearSignaturePad() {
        if (signaturePad) signaturePad.clear();
    }

    document.getElementById('save-draw-btn').addEventListener('click', function() {
        if (signaturePad.isEmpty()) {
            alert("กรุณาวาดลายเซ็นก่อนบันทึก");
            return;
        }

        const dataURL = signaturePad.toDataURL('image/png');
        const btn = this;
        const originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="bi bi-hourglass-split mr-2"></i> กำลังบันทึก...';
        btn.disabled = true;

        const formData = new URLSearchParams();
        formData.append('action', 'draw');
        formData.append('signature_data', dataURL);

        fetch('save_signature.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(response => response.text()) // Get raw text first
        .then(rawText => {
            console.log("RAW PHP RESPONSE:", rawText); // Debug print
            try {
                const data = JSON.parse(rawText);
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert("เกิดข้อผิดพลาด: " + data.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (e) {
                console.error("JSON Parse Error on:", rawText);
                alert("ระบบขัดข้อง: เซิร์ฟเวอร์ส่งข้อมูลกลับมาผิดปกติ (Please check console)");
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("เกิดข้อผิดพลาดในการเชื่อมต่อ");
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });
</script>

<?php
$content = ob_get_clean();
include 'base.php';
?>
