<?php
ob_start();
require_once 'auth_check.php';
require_role([1, 2]);
include 'edoc-db.php';
?>

<div class="py-2">
    <?php include 'base_user_status.php' ?>

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="bi bi-inbox text-amber-500"></i> งานสารบรรณ (จัดการข้อมูลการลงนาม)
        </h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse datatable text-sm">
                    <thead class="bg-slate-800 text-white text-sm">
                        <tr>
                            <th class="px-4 py-3 font-medium rounded-tl-lg">เลขที่</th>
                            <th class="px-4 py-3 font-medium">ประเภท</th>
                            <th class="px-4 py-3 font-medium">วันที่รับเข้า</th>
                            <th class="px-4 py-3 font-medium">จาก</th>
                            <th class="px-4 py-3 font-medium">เรื่อง</th>
                            <th class="px-4 py-3 font-medium">ไฟล์หนังสือ</th>
                            <th class="px-4 py-3 font-medium text-center rounded-tr-lg">ดำเนินการลงนาม</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php
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
                            $doc_id = $row['doc_id'];
                            echo "<tr class='hover:bg-gray-50/50 transition-colors'>
                                <td class='px-4 py-3 font-medium text-gray-900'>{$row['doc_no']}</td>
                                <td class='px-4 py-3'><span class='inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-slate-100 text-slate-700'>{$row['doc_type_name']}</span></td>
                                <td class='px-4 py-3 text-sm'>".formatThaiDate($row['doc_upload_date'])."</td>
                                <td class='px-4 py-3 text-sm'>{$row['doc_from']}</td>
                                <td class='px-4 py-3'>{$row['doc_name']}</td>";

                            $fileSql = "SELECT file_id, file_path FROM document_files WHERE doc_id = ?";
                            $fileStmt = $conn->prepare($fileSql);
                            $fileStmt->bind_param('i', $doc_id);
                            $fileStmt->execute();
                            $fileResult = $fileStmt->get_result();

                            echo "<td class='px-4 py-3'>";
                            if ($fileResult->num_rows > 0) {
                                $c = 1;
                                while ($f = $fileResult->fetch_assoc()) {
                                    echo "<a href='{$f['file_path']}' target='_blank' class='inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-sky-700 bg-sky-50 border border-sky-200 hover:bg-sky-100 rounded-lg transition-colors mr-1 mb-1'><i class='bi bi-file-pdf'></i> ไฟล์ {$c}</a>";
                                    $c++;
                                }
                            } else {
                                echo "<span class='text-gray-400 text-sm'>ไม่มีไฟล์</span>";
                            }
                            echo "</td>";

                            echo "<td class='px-4 py-3 text-center'>";
                            if ($fileResult->num_rows > 0) {
                                $fileResult->data_seek(0);
                                $c = 1;
                                while ($f = $fileResult->fetch_assoc()) {
                                    $file_id = $f['file_id'];
                                    $statusSql = "SELECT sign_sarabun, doc_status FROM sign_doc WHERE doc_id = ?";
                                    $statusStmt = $conn->prepare($statusSql);
                                    $statusStmt->bind_param('i', $doc_id);
                                    $statusStmt->execute();
                                    $statusResult = $statusStmt->get_result();
                                    $isApproved = false;
                                    if ($statusResult->num_rows > 0) {
                                        $s = $statusResult->fetch_assoc();
                                        if ($s['doc_status'] == 'approve') $isApproved = true;
                                    }

                                    if ($isApproved) {
                                        echo "<a href='document_preview.php?doc_id={$doc_id}&file_id={$file_id}' class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors mb-1'><i class='bi bi-eye'></i> ดูเอกสาร{$c}</a><br>";
                                    } else {
                                        echo "<div class='flex items-center justify-center gap-1 mb-1'>
                                                <a href='sarabun_sign.php?doc_id={$doc_id}&file_id={$file_id}' class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 rounded-lg transition-colors'><i class='bi bi-stamp'></i> ลงรับ {$c}</a>
                                                <a href='sarabun_signtxt.php?doc_id={$doc_id}&file_id={$file_id}' class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 hover:bg-blue-100 rounded-lg transition-colors'><i class='bi bi-pencil-square'></i> เกษียณ {$c}</a>
                                              </div>";
                                    }
                                    $c++;
                                }
                            }
                            echo "</td></tr>";
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
