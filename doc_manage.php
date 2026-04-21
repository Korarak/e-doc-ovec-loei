<?php
ob_start();
require_once 'auth_check.php';
// auth_check.php already handles session_start and basic checks.
require_role([1]); // Restricted to Administrator only.

include 'edoc-db.php';
?>
<div class="py-2">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="bi bi-file-earmark-text text-brand-600"></i> จัดการหนังสือราชการ
        </h2>
        <a href="doc_add.php" class="bg-brand-600 hover:bg-brand-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center gap-2 transition-all shadow-sm">
            <i class="bi bi-plus-circle"></i> เพิ่มหนังสือใหม่
        </a>
    </div>

    <!-- DataTables Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse datatable text-sm sm:text-base">
                    <thead class="bg-slate-800 text-white text-sm">
                        <tr>
                            <th class="px-4 py-3 font-medium rounded-tl-lg whitespace-nowrap">วันที่รับเข้า</th>
                            <th class="px-4 py-3 font-medium whitespace-nowrap">ประเภท</th>
                            <th class="px-4 py-3 font-medium whitespace-nowrap">เลขที่หนังสือ</th>
                            <th class="px-4 py-3 font-medium whitespace-nowrap">จาก</th>
                            <th class="px-4 py-3 font-medium">เรื่อง</th>
                            <th class="px-4 py-3 font-medium whitespace-nowrap">ไฟล์หนังสือ</th>
                            <th class="px-4 py-3 font-medium whitespace-nowrap">ผู้อัปโหลด</th>
                            <th class="px-4 py-3 font-medium text-center rounded-tr-lg whitespace-nowrap" width="150">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php
                        // Fetch documents scoped by institution.
                        $inst_id = $_SESSION['inst_id'];
                        $sql = "SELECT d.*, dt.doc_type_name, u.fullname 
                                FROM documents d
                                JOIN document_types dt ON d.doc_type_id = dt.doc_type_id
                                JOIN user u ON d.doc_uploader = u.user_id 
                                WHERE d.inst_id = ?
                                ORDER BY d.doc_upload_date DESC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $inst_id);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        while ($row = $result->fetch_assoc()) {
                            echo "<tr class='hover:bg-gray-50/50 transition-colors'>
                                <td class='px-4 py-3 whitespace-nowrap'>".formatThaiDate($row['doc_upload_date'])."</td>
                                <td class='px-4 py-3 whitespace-nowrap'><span class='inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200'>{$row['doc_type_name']}</span></td>
                                <td class='px-4 py-3 whitespace-nowrap font-medium text-gray-900'>{$row['doc_no']}</td>
                                <td class='px-4 py-3 text-gray-600'>{$row['doc_from']}</td>
                                <td class='px-4 py-3 text-gray-800 min-w-[200px]'>{$row['doc_name']}</td>
                                <td class='px-4 py-3 whitespace-nowrap'>";
                    
                            // Fetch associated files
                            $doc_id = $row['doc_id'];
                            $fileSql = "SELECT file_path FROM document_files WHERE doc_id = ?";
                            $fStmt = $conn->prepare($fileSql);
                            $fStmt->bind_param("i", $doc_id);
                            $fStmt->execute();
                            $fileResult = $fStmt->get_result();

                            if ($fileResult->num_rows > 0) {
                                $fCount = 1;
                                while ($fileRow = $fileResult->fetch_assoc()) {
                                    echo "<a href='{$fileRow['file_path']}' target='_blank' class='inline-flex items-center justify-center gap-1 px-2.5 py-1 text-xs font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded hover:bg-sky-100 transition-colors mb-1 mr-1'><i class='bi bi-file-pdf'></i> ไฟล์ {$fCount}</a><br>";
                                    $fCount++;
                                }
                            } else {
                                echo "<span class='text-gray-400 text-sm'>ไม่มีไฟล์</span>";
                            }
                            $fStmt->close();

                            echo "</td>
                                <td class='px-4 py-3 whitespace-nowrap text-sm text-gray-500'>{$row['fullname']}</td>
                                <td class='px-4 py-3 whitespace-nowrap text-center'>
                                    <div class='flex items-center justify-center gap-2'>
                                        <a href='doc_edit.php?id={$row['doc_id']}' class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 hover:text-amber-800 rounded-lg transition-colors'><i class='bi bi-pencil'></i> แก้ไข</a>
                                        <button onclick=\"window.confirmDelete('doc_delete.php?id={$row['doc_id']}')\" class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 border border-red-200 hover:bg-red-100 hover:text-red-800 rounded-lg transition-colors'><i class='bi bi-trash'></i> ลบ</button>
                                    </div>
                                </td>
                            </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'base.php';
?>
