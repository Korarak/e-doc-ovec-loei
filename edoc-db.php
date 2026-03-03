<?php
$servername = getenv('MYSQL_HOST');
$username = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');
$dbname = getenv('MYSQL_DATABASE');


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8"); // Set charset to utf8 for Thai language support

date_default_timezone_set('Asia/Bangkok');

?>
<?php
function formatThaiDate($date) {
    $months = [
        "01" => "มกราคม",
        "02" => "กุมภาพันธ์",
        "03" => "มีนาคม",
        "04" => "เมษายน",
        "05" => "พฤษภาคม",
        "06" => "มิถุนายน",
        "07" => "กรกฎาคม",
        "08" => "สิงหาคม",
        "09" => "กันยายน",
        "10" => "ตุลาคม",
        "11" => "พฤศจิกายน",
        "12" => "ธันวาคม"
    ];
    $year = date('Y', strtotime($date)) + 543;
    $month = $months[date('m', strtotime($date))];
    $day = date('d', strtotime($date));
    $time = date('H.i น.', strtotime($date));
    
    return "$day $month $year";
}
?>
<?php
function formatThaiTime($date) {
    // แปลงเวลา
    $time = date('H.i น.', strtotime($date)); // เวลาในรูปแบบ 24 ชั่วโมง

    // ส่งคืนเวลา
    return $time;
}