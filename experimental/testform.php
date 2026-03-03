<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ฟอร์ม</title>
    <style>
        #signatureImage {
            display: none;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <!-- ฟอร์มแรกสำหรับกรอกข้อมูล -->
    <form id="myForm">
        <p>เรียน ผอ. วท.เลย</p>
        <label><input type="checkbox" name="action" value="ทราบ"> เพื่อโปรดทราบ</label><br>
        <label><input type="checkbox" name="action" value="พิจารณา"> เพื่อโปรดพิจารณา</label><br>
        <label><input type="checkbox" name="action" value="แจ้ง"> เห็นควรแจ้ง</label><br>
        <label><input type="checkbox" name="action" value="มอบ"> เห็นควรมอบ</label><br>
        <label><input type="checkbox" id="signCheckbox" name="action" value="ลงนาม" onclick="toggleSignature()"> ลงนาม</label><br>
        <p>ความเห็น</p>
        <textarea name="comments" rows="4" cols="50"></textarea><br>
        <button type="button" onclick="submitForm()">Submit</button>
    </form>

    <!-- ฟอร์มที่สองสำหรับแสดงผลลัพธ์ -->
    <form id="outputForm">
        <p>ผลลัพธ์:</p>
        <textarea id="outputTextarea" rows="10" cols="50" readonly></textarea><br>
        <img id="signatureImage" src="w644.png" alt="Signature">
    </form>

    <script>
        function submitForm() {
            var form = document.getElementById('myForm');
            var checkboxes = form.querySelectorAll('input[type="checkbox"]');
            var comments = form.querySelector('textarea[name="comments"]').value;
            var result = 'เรียน ผอ. วท.เลย\n';

            // ตรวจสอบ checkbox ที่ถูกเลือก
            var action1 = 'เพื่อโปรด ';
            var action2 = 'เห็นควร ';
            var subResult1 = [];
            var subResult2 = [];

            checkboxes.forEach(function(checkbox) {
                if (checkbox.checked && checkbox.value !== "ลงนาม") {
                    if (checkbox.value === 'ทราบ' || checkbox.value === 'พิจารณา') {
                        subResult1.push('' + checkbox.value);
                    } else if (checkbox.value === 'แจ้ง' || checkbox.value === 'มอบ') {
                        subResult2.push('' + checkbox.value);
                    }
                }
            });

            if (subResult1.length > 0) {
                result += action1 + subResult1.join(' ') + '\n';
            }
            if (subResult2.length > 0) {
                result += action2 + subResult2.join(' ') + '\n';
            }

            result += '\n"' + comments + '"';

            // แสดงผลใน textarea ของฟอร์มที่สอง
            document.getElementById('outputTextarea').value = result;

            // แสดงรูปภาพถ้า "ลงนาม" ถูกเลือก
            var signatureCheckbox = document.getElementById('signCheckbox');
            var signatureImage = document.getElementById('signatureImage');
            if (signatureCheckbox.checked) {
                signatureImage.style.display = 'block';
            } else {
                signatureImage.style.display = 'none';
            }
        }

        function toggleSignature() {
            var signatureCheckbox = document.getElementById('signCheckbox');
            var signatureImage = document.getElementById('signatureImage');
            if (signatureCheckbox.checked) {
                signatureImage.style.display = 'block';
            } else {
                signatureImage.style.display = 'none';
            }
        }
    </script>
</body>
</html>
