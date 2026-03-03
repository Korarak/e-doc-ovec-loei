<?php
require_once 'vendor/autoload.php';

// รับข้อมูลจากฟอร์ม
$inputText = $_POST['input-text'];
$xPos = $_POST['x-pos'];
$yPos = $_POST['y-pos'];
$inputDate = $_POST['input-date'];
$inputImage = $_POST['input-image'];

// เส้นทางไปยัง PDF ต้นฉบับ
$originalPdfPath = 'doc2.pdf';

// สร้าง mPDF object
$mpdf = new \Mpdf\Mpdf([
    'default_font' => 'sarabun'
]);

// Import PDF ต้นฉบับ
$pageCount = $mpdf->SetSourceFile($originalPdfPath);
$tplIdx = $mpdf->ImportPage(1);

// เพิ่มหน้าที่มีการ Import ไว้
$mpdf->UseTemplate($tplIdx);
$inputText = nl2br(htmlspecialchars($inputText));
// HTML ที่ใช้กำหนดตำแหน่งและสไตล์ของข้อความ
$html = "<div style=\"position:absolute;top:{$yPos}px;left:{$xPos}px;width:auto\">
{$inputText}
<br>
{$inputDate}
<br>
<img src='{$inputImage}' alt='' width='50' height='50'>
</div>";

// เขียน HTML ลงใน PDF
$mpdf->WriteHTML($html);

// บันทึก PDF
$outputPdfPath = 'output.pdf'; // เปลี่ยนเส้นทางตามที่ต้องการ
$mpdf->Output($outputPdfPath, \Mpdf\Output\Destination::FILE);

// แสดงไฟล์ PDF ที่สร้างขึ้น
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="output.pdf"');
readfile($outputPdfPath);

?>
