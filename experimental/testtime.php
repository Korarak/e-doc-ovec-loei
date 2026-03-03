<?php
    date_default_timezone_set('Asia/Bangkok');

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
                return "$day $month $year";
            }

            function formatThaiTime($datetime) {
                return date('H:i น.', strtotime($datetime));
            }
?>

<?php
    echo "วันที่ " . formatThaiDate(date('Y-m-d'));
?>

<?php
    echo "เวลา " . formatThaiTime(date('Y-m-d H:i:s'));
?>