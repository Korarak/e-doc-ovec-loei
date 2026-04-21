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
                            <th class="px-4 py-3 text-center">ประเภท</th>
                            <th class="px-4 py-3 text-center">วันที่</th>
                            <th class="px-4 py-3">จาก</th>
                            <th class="px-4 py-3">เรื่อง</th>
                            <th class="px-4 py-3">ไฟล์</th>
                            <th class="px-4 py-3 text-center">สถานะ</th>
                            <th class="px-4 py-3 text-center">ดำเนินการลงนาม</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php
                        $inst_id = $_SESSION['inst_id'];
                        $sql = "SELECT d.*, dt.doc_type_name, u.fullname, sd.sign_sarabun, sd.doc_status, sd.dep_id as target_dep, dep.dep_name
                                FROM documents d
                                JOIN document_types dt ON d.doc_type_id = dt.doc_type_id
                                JOIN user u ON d.doc_uploader = u.user_id
                                LEFT JOIN sign_doc sd ON d.doc_id = sd.doc_id
                                LEFT JOIN department dep ON sd.dep_id = dep.dep_id
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
                             $sign_sarabun = $row['sign_sarabun'] ?? 'pending';
                             $dep_name_hint = !empty($row['dep_name']) ? " (" . htmlspecialchars($row['dep_name']) . ")" : "";
                             
                             if ($sign_sarabun == 'approve') {
                                 echo "<span class='inline-flex items-center gap-1 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold border border-emerald-200'><i class='bi bi-check-circle-fill'></i> เกษียณแล้ว{$dep_name_hint}</span>";
                             } elseif ($sign_sarabun == 'stamp_done') {
                                 echo "<span class='inline-flex items-center gap-1 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold border border-amber-200'><i class='bi bi-info-circle-fill'></i> ลงรับแล้ว{$dep_name_hint}</span>";
                             } else {
                                 echo "<span class='inline-flex items-center gap-1 px-3 py-1 bg-slate-100 text-slate-500 rounded-full text-xs font-bold border border-slate-200'><i class='bi bi-clock-history'></i> รอลงรับ</span>";
                             }
                             echo "</td>";

                             echo "<td class='px-4 py-3 text-center'>";
                             if ($fileResult->num_rows > 0) {
                                 $fileResult->data_seek(0);
                                 $c = 1;
                                 while ($f = $fileResult->fetch_assoc()) {
                                     $file_id = $f['file_id'];
                                     
                                     if ($sign_sarabun == 'approve') {
                                         echo "<div class='flex items-center justify-center gap-1 mb-1'>";
                                         echo "<a href='document_preview.php?doc_id={$doc_id}&file_id={$file_id}' class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg transition-colors shadow-sm'><i class='bi bi-eye'></i> ดูเอกสาร{$c}</a>";
                                         if ($_SESSION['role_id'] == 1) {
                                             echo "<a href='sarabun_sign.php?doc_id={$doc_id}&file_id={$file_id}' title='(แอดมิน) แก้ไขการลงรับ' class='inline-flex items-center justify-center px-2 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 rounded-lg transition-colors'><i class='bi bi-pencil'></i> แก้ไข</a>";
                                         }
                                         echo "</div>";
                                     } else {
                                         echo "<div class='flex items-center justify-center gap-1 mb-1'>";
                                         
                                         // ปุ่มลงรับ
                                         if ($sign_sarabun == 'stamp_done') {
                                             echo "<span class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg'><i class='bi bi-check'></i> ลงรับแล้ว </span>";
                                             if ($_SESSION['role_id'] == 1) {
                                                 echo "<a href='sarabun_sign.php?doc_id={$doc_id}&file_id={$file_id}' title='(แอดมิน) แก้ไขการลงรับ' class='inline-flex items-center justify-center px-2 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 rounded-lg transition-colors'><i class='bi bi-pencil'></i></a>";
                                             }
                                         } else {
                                             echo "<a href='sarabun_sign.php?doc_id={$doc_id}&file_id={$file_id}' class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 rounded-lg transition-colors'><i class='bi bi-stamp'></i> ลงรับ{$c}</a>";
                                         }
                                         
                                         // ปุ่มเกษียณ
                                         if ($sign_sarabun == 'stamp_done') {
                                             echo "<a href='sarabun_signtxt.php?doc_id={$doc_id}&file_id={$file_id}' class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 hover:bg-blue-100 rounded-lg transition-colors'><i class='bi bi-pencil-square'></i> เกษียณ{$c}</a>";
                                         } else {
                                             echo "<span class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-400 bg-gray-50 border border-gray-200 rounded-lg cursor-not-allowed' title='ต้องทำรายการลงรับก่อนทำการเกษียณ'><i class='bi bi-pencil-square'></i> เกษียณ{$c}</span>";
                                         }
                                         echo "</div>";
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
