<?php
require_once 'vendor/autoload.php';

// รับข้อมูลจากฟอร์ม
$inputText = $_POST['input-text'];
$xPos = $_POST['x-pos'];
$yPos = $_POST['y-pos'];
$inputDate = $_POST['input-date'];
$inputImage = $_POST['input-image'];
$pageNum = $_POST['page-num'];

// เส้นทางไปยัง PDF ต้นฉบับ
$originalPdfPath = 'test.pdf';

// สร้าง mPDF object
$mpdf = new \Mpdf\Mpdf([
    'default_font' => 'sarabun'
]);

// Import PDF ต้นฉบับ
$pageCount = $mpdf->SetSourceFile($originalPdfPath);

for ($i = 1; $i <= $pageCount; $i++) {
    $tplIdx = $mpdf->ImportPage($i);
    $mpdf->UseTemplate($tplIdx);

    // หากเป็นหน้าที่ต้องการใส่ข้อมูล
    if ($i == $pageNum) {
        $inputTextFormatted = nl2br(htmlspecialchars($inputText));
        // HTML ที่ใช้กำหนดตำแหน่งและสไตล์ของข้อความ
        $html = "<div style=\"position:absolute;top:{$yPos}px;left:{$xPos}px;width:auto\">
                    {$inputTextFormatted}
                    <br>
                    {$inputDate}
                    <br>
                    <img src='{$inputImage}' alt='' width='50' height='50'>
                 </div>";

        // เขียน HTML ลงใน PDF
        $mpdf->WriteHTML($html);
    }

    // เพิ่มหน้าต่อไปถ้าไม่ใช่หน้าสุดท้าย
    if ($i < $pageCount) {
        $mpdf->AddPage();
    }
}

// บันทึก PDF
$outputPdfPath = 'output.pdf'; // เปลี่ยนเส้นทางตามที่ต้องการ
$mpdf->Output($outputPdfPath, \Mpdf\Output\Destination::FILE);

// แสดงไฟล์ PDF ที่สร้างขึ้น
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="output.pdf"');
readfile($outputPdfPath);
?>
