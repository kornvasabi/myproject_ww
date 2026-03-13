<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

// 1. เรียกใช้งาน Composer Autoload (เหมือนหน้า PDF เลยครับ)
require_once __DIR__ . '/vendor/autoload.php'; 
require_once 'db.php'; 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// รับค่าจาก Form
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');

// ดึงข้อมูล
$sql = "SELECT * FROM issues 
        WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' 
        ORDER BY created_at ASC, id ASC";
$result = $conn->query($sql);

// 2. สร้างไฟล์ Excel เปล่าๆ ขึ้นมา
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// --- ตั้งค่า Font มาตรฐานทั้งชีตเป็น TH SarabunPSK ไซส์ 16 ---
$spreadsheet->getDefaultStyle()->getFont()->setName('TH SarabunPSK')->setSize(16);

// --- หัวกระดาษ ---
$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', 'รายงานสรุปข้อมูลประจำวัน');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(20);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A2:D2');
$sheet->setCellValue('A2', "ตั้งแต่วันที่ " . date('d/m/Y', strtotime($start_date)) . " ถึง " . date('d/m/Y', strtotime($end_date)));
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A3:D3');
$sheet->setCellValue('A3', "พิมพ์เมื่อ: " . date('d/m/Y H:i:s'));
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// --- หัวตารางข้อมูล ---
$sheet->setCellValue('A5', 'ID');
$sheet->setCellValue('B5', 'หัวข้อปัญหา (Title)');
$sheet->setCellValue('C5', 'รายละเอียด (Description)');
$sheet->setCellValue('D5', 'วันที่บันทึก (Date)');

// จัดสีพื้นหลัง ทำตัวหนา และจัดกึ่งกลางให้หัวตาราง
$headerStyle = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFD9EDF7'] // สีฟ้าน้ำทะเลอ่อนๆ
    ]
];
$sheet->getStyle('A5:D5')->applyFromArray($headerStyle);

// --- วนลูปใส่ข้อมูล ---
$row_num = 6; // เริ่มใส่ข้อมูลที่บรรทัด 6
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row_num, $row['id']);
        $sheet->setCellValue('B' . $row_num, $row['title']);
        $sheet->setCellValue('C' . $row_num, $row['description']);
        $sheet->setCellValue('D' . $row_num, date('d/m/Y H:i', strtotime($row['created_at'])));

        // จัดกึ่งกลางคอลัมน์ ID และ วันที่
        $sheet->getStyle('A'.$row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D'.$row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row_num++;
    }
} else {
    $sheet->mergeCells("A$row_num:D$row_num");
    $sheet->setCellValue("A$row_num", 'ไม่พบข้อมูลในช่วงวันที่เลือก');
    $sheet->getStyle("A$row_num")->getFont()->getColor()->setARGB('FFFF0000'); // สีแดง
    $sheet->getStyle("A$row_num")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row_num++;
}

// --- ตีเส้นขอบตาราง (Hairline = เส้นบางที่สุดใน Excel) ---
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_HAIR, 
            'color' => ['argb' => 'FF000000'],
        ],
    ],
];
$sheet->getStyle('A5:D' . ($row_num - 1))->applyFromArray($styleArray);

// --- ปรับความกว้างคอลัมน์อัตโนมัติ (ให้พอดีกับข้อความ) ---
$sheet->getColumnDimension('A')->setAutoSize(true);
$sheet->getColumnDimension('B')->setAutoSize(true);
$sheet->getColumnDimension('C')->setAutoSize(true);
$sheet->getColumnDimension('D')->setAutoSize(true);

// 3. ส่งออกเป็นไฟล์ .xlsx แท้ 100%
$filename = "Daily_Report_" . str_replace('-', '', $start_date) . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0'); // ไม่ให้ Browser จำ Cache

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

// ไม่จำเป็นต้องใช้คำสั่ง mysqli_close() หรือคืน Memory ตรงนี้ เพราะโครงสร้างของ PHP 8.5 จะจัดการเก็บกวาดให้เราเองอัตโนมัติเมื่อสคริปต์นี้ทำงานเสร็จครับ
exit;