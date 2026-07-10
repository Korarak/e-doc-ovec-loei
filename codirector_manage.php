<?php
ob_start();
require_once 'auth_check.php';
require_role([1, 2, 3]);
include 'edoc-db.php';
?>

<div class="py-2">
    <?php include 'base_user_status.php' ?>

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="bi bi-person-fill-check text-sky-500"></i> รองผู้อำนวยการ (เอกสารรอดำเนินการฝ่าย: <?php echo htmlspecialchars($_SESSION['dep_name'] ?? 'ไม่ระบุ'); ?>)
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
                            <th class="px-4 py-3 font-medium text-center">ตรวจสอบ</th>
                            <th class="px-4 py-3 font-medium text-center rounded-tr-lg">ดำเนินการ (เกษียณ)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php
                        $inst_id = $_SESSION['inst_id'];
                        $sql = "SELECT d.*, dt.doc_type_name, u.fullname, sd.sign_codirector
                                FROM documents d
                                JOIN document_types dt ON d.doc_type_id = dt.doc_type_id
                                JOIN user u ON d.doc_uploader = u.user_id
                                JOIN sign_doc sd ON d.doc_id = sd.doc_id
                                WHERE d.inst_id = ? AND sd.dep_id = ? AND sd.doc_status = 'approve' AND sd.sign_sarabun = 'approve'
                                ORDER BY d.doc_upload_date DESC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('ii', $inst_id, $_SESSION['dep_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        // Load every attached file for this institution in one query
                        // instead of one query per document row.
                        $filesByDoc = [];
                        $fileStmt = $conn->prepare("SELECT df.doc_id, df.file_id, df.file_path FROM document_files df JOIN documents d ON df.doc_id = d.doc_id WHERE d.inst_id = ? ORDER BY df.file_id");
                        $fileStmt->bind_param('i', $inst_id);
                        $fileStmt->execute();
                        $fRes = $fileStmt->get_result();
                        while ($f = $fRes->fetch_assoc()) {
                            $filesByDoc[$f['doc_id']][] = $f;
                        }
                        $fileStmt->close();

                        while ($row = $result->fetch_assoc()) {
                            $doc_id = $row['doc_id'];
                            $docFiles = $filesByDoc[$doc_id] ?? [];
                            $isCoDirApproved = ($row['sign_codirector'] === 'approve');

                            echo "<tr class='hover:bg-gray-50/50 transition-colors'>
                                <td class='px-4 py-3 font-medium text-gray-900'>".htmlspecialchars($row['doc_no'])."</td>
                                <td class='px-4 py-3'><span class='inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-slate-100 text-slate-700'>".htmlspecialchars($row['doc_type_name'])."</span></td>
                                <td class='px-4 py-3 text-sm'>".formatThaiDate($row['doc_upload_date'])."</td>
                                <td class='px-4 py-3 text-sm'>".htmlspecialchars($row['doc_from'] ?? '')."</td>
                                <td class='px-4 py-3'>".htmlspecialchars($row['doc_name'])."</td>";

                            // File column
                            echo "<td class='px-4 py-3'>";
                            if (!empty($docFiles)) {
                                $c = 1;
                                foreach ($docFiles as $f) {
                                    echo "<a href='".htmlspecialchars($f['file_path'])."' target='_blank' class='inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-sky-700 bg-sky-50 border border-sky-200 hover:bg-sky-100 rounded-lg transition-colors mr-1 mb-1'><i class='bi bi-file-pdf'></i> ไฟล์ {$c}</a>";
                                    $c++;
                                }
                            } else {
                                echo "<span class='text-gray-400 text-sm'>ไม่มีไฟล์</span>";
                            }
                            echo "</td>";

                            // Preview column
                            echo "<td class='px-4 py-3 text-center'>";
                            if (!empty($docFiles)) {
                                $c = 1;
                                foreach ($docFiles as $f) {
                                    echo "<a href='document_preview.php?doc_id={$doc_id}&file_id={$f['file_id']}' class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors mb-1'><i class='bi bi-eye'></i> ดูเอกสาร {$c}</a><br>";
                                    $c++;
                                }
                            }
                            echo "</td>";

                            // Action column
                            echo "<td class='px-4 py-3 text-center'>";
                            if (!empty($docFiles)) {
                                $c = 1;
                                foreach ($docFiles as $f) {
                                    if ($isCoDirApproved) {
                                        echo "<span class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-green-700 bg-green-100 rounded-lg mb-1'><i class='bi bi-check-circle'></i> ดำเนินการแล้ว</span><br>";
                                    } else {
                                        echo "<a href='codirector_sign.php?doc_id={$doc_id}&file_id={$f['file_id']}' class='inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 rounded-lg transition-colors mb-1'><i class='bi bi-pencil-square'></i> เกษียณ {$c}</a><br>";
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
