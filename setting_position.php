<?php
ob_start();
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
include 'edoc-db.php';
$inst_id = $_SESSION['inst_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $name = trim($_POST['item_name']);
    $stmt = $conn->prepare("INSERT INTO position (inst_id, position_name) VALUES (?, ?)");
    $stmt->bind_param("is", $inst_id, $name);
    $stmt->execute();
    header("Location: setting_position.php?msg=success"); exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = intval($_POST['item_id']);
    $name = trim($_POST['item_name']);
    $stmt = $conn->prepare("UPDATE position SET position_name = ? WHERE position_id = ? AND inst_id = ?");
    $stmt->bind_param("sii", $name, $id, $inst_id);
    $stmt->execute();
    header("Location: setting_position.php?msg=success"); exit;
}
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $conn->prepare("DELETE FROM position WHERE position_id = ? AND inst_id = ?");
        $stmt->bind_param("ii", $id, $inst_id);
        if ($stmt->execute()) {
            header("Location: setting_position.php?msg=success");
        } else {
            header("Location: setting_position.php?error=db_error");
        }
        exit;
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1451) {
            header("Location: setting_position.php?error=fk_error");
        } else {
            header("Location: setting_position.php?error=db_error");
        }
        exit;
    }
}

$stmt = $conn->prepare("SELECT position_id, position_name FROM position WHERE inst_id = ? ORDER BY position_id");
$stmt->bind_param("i", $inst_id);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) $items[] = $row;
$stmt->close();
?>

<div class="py-2">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="bi bi-person-badge text-brand-600"></i> ตั้งค่าตำแหน่ง
        </h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
        <form method="POST" class="flex gap-3">
            <input type="text" name="item_name" placeholder="เพิ่มตำแหน่งใหม่" required class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
            <button type="submit" name="add" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2.5 px-5 rounded-lg transition-colors shadow-sm inline-flex items-center gap-1">
                <i class="bi bi-plus-lg"></i> เพิ่ม
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6">
            <table class="w-full text-left border-collapse text-sm">
                <thead class="bg-slate-800 text-white text-sm">
                    <tr>
                        <th class="px-4 py-3 font-medium rounded-tl-lg w-20">ID</th>
                        <th class="px-4 py-3 font-medium">ชื่อตำแหน่ง</th>
                        <th class="px-4 py-3 font-medium text-center rounded-tr-lg w-48">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    <?php foreach ($items as $item): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3 text-gray-500"><?= $item['position_id'] ?></td>
                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars($item['position_name']) ?></td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="openModal('editModal<?= $item['position_id'] ?>')" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 rounded-lg transition-colors">
                                    <i class="bi bi-pencil"></i> แก้ไข
                                </button>
                                <button onclick="confirmDelete('setting_position.php?delete=<?= $item['position_id'] ?>')" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 border border-red-200 hover:bg-red-100 rounded-lg transition-colors">
                                    <i class="bi bi-trash"></i> ลบ
                                </button>
                            </div>
                            <div id="editModal<?= $item['position_id'] ?>" class="modal-overlay hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                                <div class="bg-white rounded-xl shadow-xl w-full max-w-md" onclick="event.stopPropagation()">
                                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                                        <h5 class="text-lg font-bold text-gray-800">แก้ไขตำแหน่ง</h5>
                                        <button onclick="closeModal('editModal<?= $item['position_id'] ?>')" class="text-gray-400 hover:text-gray-600"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                    <form method="POST" class="p-6">
                                        <input type="hidden" name="item_id" value="<?= $item['position_id'] ?>">
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อตำแหน่ง</label>
                                            <input type="text" name="item_name" value="<?= htmlspecialchars($item['position_name']) ?>" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none">
                                        </div>
                                        <div class="flex justify-end gap-3">
                                            <button type="button" onclick="closeModal('editModal<?= $item['position_id'] ?>')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">ยกเลิก</button>
                                            <button type="submit" name="edit" class="px-4 py-2 text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 rounded-lg shadow-sm">บันทึก</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'base.php';
?>