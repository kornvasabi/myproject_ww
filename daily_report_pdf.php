<?php
session_start();
// ปิด Error ทั้งหมดชั่วคราว เพื่อให้ PDF สร้างได้
error_reporting(0); 
ini_set('display_errors', 0);
// เรียก Composer Autoload
require_once __DIR__ . '/vendor/autoload.php'; 
require_once 'db.php'; // เชื่อมต่อ Database

// 1. รับค่าจาก Form
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');

// 2. ดึงข้อมูลจากตาราง daily_report
// (ปรับ SQL ตามชื่อคอลัมน์จริงของคุณ)
$sql = "SELECT * FROM issues 
        WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' 
        ORDER BY created_at ASC, id ASC";
// echo $sql; exit;
$result = $conn->query($sql);
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// 3. เตรียมข้อมูลส่วนหัว
$report_header = [
    'title' => 'รายงานสรุปข้อมูลประจำวัน',
    'range' => "ตั้งแต่วันที่ " . date('d/m/Y', strtotime($start_date)) . " ถึง " . date('d/m/Y', strtotime($end_date)),
    'print_date' => date('d/m/Y H:i:s')
];

// 4. โหลด HTML Template (แยกไฟล์เพื่อความสะอาด)
ob_start();
include 'daily_report_template.php'; 
$html = ob_get_contents();
ob_end_clean();

// 5. สร้าง PDF ด้วย mPDF
try {
    // ตั้งค่า Font ภาษาไทย
    $defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];

    $defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 
        'format' => 'A4',
        'margin_top' => 10,
        'margin_left' => 10, 
        'margin_right' => 10,
        'fontDir' => array_merge($fontDirs, [ __DIR__ . '/fonts' ]), // โฟลเดอร์ฟอนต์
        'fontdata' => $fontData + [
            'sarabun' => [
                'R' => 'THSarabun.ttf', // ชื่อไฟล์ฟอนต์จริง
                'B' => 'THSarabun Bold.ttf',
            ]
        ],
        'default_font' => 'sarabun'
    ]);

    $mpdf->WriteHTML($html);
    // $mpdf->Output('daily_report.pdf', 'I'); // I = View, D = Download
    // ของใหม่: ส่งเป็น String Binary กลับไปให้ JS

    $pdfContent = $mpdf->Output('', 'S'); 
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($pdfContent));
    header('Content-Disposition: inline; filename="daily_report.pdf"');
    echo $pdfContent;

} catch (\Mpdf\MpdfException $e) {
    echo $e->getMessage();
}
?>