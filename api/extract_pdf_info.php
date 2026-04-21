<?php
ob_start();
require_once '../auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['image_base64'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing image data']);
    exit();
}

$imageBase64 = $input['image_base64'];
if (str_contains($imageBase64, ',')) {
    $imageBase64 = explode(',', $imageBase64)[1];
}

$method   = $input['method']    ?? 'ocr';
$mimeType = $input['mime_type'] ?? 'image/png';

/* ──────────────────────────────────────────────────
   Gemini AI Branch
────────────────────────────────────────────────── */
if ($method === 'gemini') {
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        echo json_encode(['success' => false, 'error' => 'GEMINI_API_KEY ยังไม่ได้ตั้งค่าในไฟล์ .env']);
        exit();
    }

    $model = getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash-preview-05-20';
    $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $prompt = <<<'PROMPT'
You are extracting metadata from a Thai government official document (หนังสือราชการ). Analyze the document image and return ONLY a valid JSON object (no markdown, no explanation) with these fields:
- "doc_from": string — the sending organization/department name found at the top of the document
- "doc_from_candidates": array of up to 5 strings — all plausible sender names found in the document header area
- "doc_name": string — the document subject, i.e. the text immediately after "เรื่อง"
- "doc_name_candidates": array of up to 3 strings — alternative readings of the subject line
- "doc_type_guess": string — one of: "หนังสือภายนอก", "หนังสือภายใน", "หนังสือสั่งการ", "หนังสือประชาสัมพันธ์"
PROMPT;

    $body = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageBase64]]
            ]
        ]],
        'generationConfig' => ['responseMimeType' => 'application/json']
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['success' => false, 'error' => 'cURL error: ' . $curlErr]);
        exit();
    }
    if ($httpCode !== 200) {
        $errData = json_decode($response, true);
        echo json_encode(['success' => false, 'error' => 'Gemini API error: ' . ($errData['error']['message'] ?? $response)]);
        exit();
    }

    $geminiResponse = json_decode($response, true);
    $text   = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $parsed = json_decode($text, true);

    if (!$parsed) {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถแปลงผลลัพธ์จาก Gemini ได้']);
        exit();
    }

    echo json_encode([
        'success'             => true,
        'doc_from'            => $parsed['doc_from'] ?? '',
        'doc_from_candidates' => $parsed['doc_from_candidates'] ?? [],
        'doc_name'            => $parsed['doc_name'] ?? '',
        'doc_name_candidates' => $parsed['doc_name_candidates'] ?? [],
        'doc_type_guess'      => $parsed['doc_type_guess'] ?? 'หนังสือภายนอก',
        'confidence'          => 0.92,
        'method'              => 'gemini',
    ]);
    exit();
}

/* ──────────────────────────────────────────────────
   Tesseract OCR Branch (default)
────────────────────────────────────────────────── */

$tempDir = sys_get_temp_dir() . '/ocr_temp_' . uniqid();
if (!mkdir($tempDir, 0777, true)) {
    echo json_encode(['success' => false, 'error' => 'Could not create temp directory']);
    exit();
}

$extMap     = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/tiff'=>'tif'];
$imgExt     = $extMap[$mimeType] ?? 'png';
$imagePath  = $tempDir . '/page.' . $imgExt;
$outputPath = $tempDir . '/output'; // Tesseract will append .txt

file_put_contents($imagePath, base64_decode($imageBase64));

// Run Tesseract OCR (Thai + English)
// -l tha+eng specifies Thai and English languages
$command = "tesseract " . escapeshellarg($imagePath) . " " . escapeshellarg($outputPath) . " -l tha+eng 2>&1";
exec($command, $outputLines, $returnVar);

if ($returnVar !== 0) {
    echo json_encode([
        'success' => false, 
        'error' => 'OCR Error (Tesseract not found or failed)', 
        'debug' => implode("\n", $outputLines)
    ]);
    // Cleanup
    @unlink($imagePath);
    @rmdir($tempDir);
    exit();
}

$ocrText = file_get_contents($outputPath . '.txt');

// --- Thai Official Document Parser ---
$doc_from_candidates = []; // ตัวเลือกหน่วยงาน หลายๆ อัน
$doc_name = "";
$doc_type_guess = "หนังสือภายนอก"; // Default

$lines = explode("\n", $ocrText);

// 1. หาหน่วยงานต้นทาง — รวบรวมหลายตัวเลือก
if (preg_match('/ส่วนราชการ\s*(.+)/u', $ocrText, $matches)) {
    // หนังสือภายในมี label ชัดเจน เอาแค่อันเดียวพอ
    $v = trim($matches[1]);
    if ($v !== '') $doc_from_candidates[] = $v;
} else {
    // หนังสือภายนอก — สำรวจบรรทัดบนสุดหลายบรรทัด เก็บตัวเลือกที่น่าเชื่อถือ
    $found = 0;
    $stop  = false;
    foreach ($lines as $line) {
        if ($stop) break;
        $cleanLine = trim($line);

        // ข้ามบรรทัดว่าง/สั้น
        if (mb_strlen($cleanLine) < 5) continue;

        // เมื่อเจอวันที่ หมายความว่าผ่านส่วนหัวมาแล้ว → หยุด
        if (preg_match('/(มกราคม|กุมภาพันธ์|มีนาคม|เมษายน|พฤษภาคม|มิถุนายน|กรกฎาคม|สิงหาคม|กันยายน|ตุลาคม|พฤศจิกายน|ธันวาคม)/u', $cleanLine)) {
            $stop = true;
            continue;
        }

        // เมื่อเจอ "เรื่อง" หมายความว่าถึงตัวเนื้อหาแล้ว → หยุด
        if (preg_match('/^เรื่อง/u', $cleanLine)) {
            $stop = true;
            continue;
        }

        // ข้ามเลขที่หนังสือ เช่น ที่ ศธ 0000/00
        if (preg_match('/^ที่\s*[\wก-๙]/u', $cleanLine)) continue;

        // ข้ามชั้นความเร็ว/ความลับ
        if (preg_match('/ด่วนที่สุด|ด่วนมาก|ด่วน|ลับที่สุด|ลับมาก|ลับ/u', $cleanLine)) continue;

        // เก็บเป็น candidate (สูงสุด 5 บรรทัด)
        if ($found < 5) {
            $doc_from_candidates[] = $cleanLine;
            $found++;
        }
    }
}

// Best guess = ตัวเลือกแรก (ถ้ามี)
$doc_from = $doc_from_candidates[0] ?? '';

// 2. หาชื่อเรื่อง (doc_name) — ต่อจากคำว่า "เรื่อง"
$doc_name_candidates = [];
if (preg_match('/เรื่อง\s*(.+)/u', $ocrText, $matches)) {
    $firstLine = trim($matches[1]);
    if ($firstLine !== '') $doc_name_candidates[] = $firstLine;
}

// ค้นหาแบบละเอียดขึ้นจาก Array บรรทัด
foreach ($lines as $idx => $line) {
    if (preg_match('/เรื่อง/u', $line)) {
        $cleanLine = trim(preg_replace('/เรื่อง\s*/u', '', $line));
        if ($cleanLine !== '' && !in_array($cleanLine, $doc_name_candidates)) {
            $doc_name_candidates[] = $cleanLine;
        }
        
        // ลองรวมบรรทัดถัดไป (เผื่อเป็นเรื่องที่ยาวมาก)
        if (isset($lines[$idx+1])) {
            $nextLine = trim($lines[$idx+1]);
            // ถ้าบรรทัดถัดไปไม่สั้นเกินไป และไม่มีคำว่า "เรียน" (ซึ่งมักจะเป็นหัวข้อถัดไป)
            if (mb_strlen($nextLine) > 5 && !preg_match('/เรียน/u', $nextLine)) {
                $combined = $cleanLine . " " . $nextLine;
                if (!in_array($combined, $doc_name_candidates)) {
                    $doc_name_candidates[] = $combined;
                }
                // เพิ่มบรรทัดที่สองเดี่ยวๆ เป็นทางเลือกด้วย เผื่อบรรทัดแรกมีขยะ
                if (!in_array($nextLine, $doc_name_candidates)) {
                    $doc_name_candidates[] = $nextLine;
                }
            }
        }
        break;
    }
}

// Best guess
$doc_name = $doc_name_candidates[0] ?? '';

// 3. ประเมินประเภทหนังสือ
if (mb_stripos($ocrText, 'บันทึกข้อความ') !== false) {
    $doc_type_guess = "หนังสือภายใน";
} elseif (mb_stripos($ocrText, 'คำสั่ง') !== false) {
    $doc_type_guess = "หนังสือสั่งการ";
} elseif (mb_stripos($ocrText, 'ประกาศ') !== false) {
    $doc_type_guess = "หนังสือประชาสัมพันธ์";
}

// Cleanup
@unlink($imagePath);
@unlink($outputPath . '.txt');
@rmdir($tempDir);

echo json_encode([
    'success'            => true,
    'doc_from'           => $doc_from,
    'doc_from_candidates' => $doc_from_candidates,
    'doc_name'           => $doc_name,
    'doc_name_candidates' => $doc_name_candidates,   // เพิ่มฟิลด์นี้
    'doc_type_guess'     => $doc_type_guess,
    'confidence'         => 0.85,
    'method'             => 'tesseract_ocr'
]);
