<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toggle Forms</title>
</head>
<body>
    <label for="formSelect">เลือกฟอร์ม:</label>
    <select id="formSelect">
        <option value="0">--กรุณาเลือก--</option>
        <option value="1">ฟอร์ม 1</option>
        <option value="2">ฟอร์ม 2</option>
    </select>

    <!-- ฟอร์ม 1 -->
    <div id="form1" style="display:none;">
        <h3>ฟอร์ม 1</h3>
        <p>นี่คือฟอร์ม 1</p>
    </div>

    <!-- ฟอร์ม 2 -->
    <div id="form2" style="display:none;">
        <h3>ฟอร์ม 2</h3>
        <p>นี่คือฟอร์ม 2</p>
    </div>

    <script>
        document.getElementById('formSelect').addEventListener('change', function() {
            var form1 = document.getElementById('form1');
            var form2 = document.getElementById('form2');
            var selectedValue = this.value;

            // ซ่อนฟอร์มทั้งหมด
            form1.style.display = 'none';
            form2.style.display = 'none';

            // แสดงฟอร์มที่เลือก
            if (selectedValue === '1') {
                form1.style.display = 'block';
            } else if (selectedValue === '2') {
                form2.style.display = 'block';
            }
        });
    </script>
</body>
</html>
