<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['price_file'])) {
    
    $file = $_FILES['price_file']['tmp_name'];
    $handle = fopen($file, "r");
    
    if ($handle === FALSE) {
        $_SESSION['msg_status'] = 'error';
        $_SESSION['msg_text'] = 'ไม่สามารถเปิดไฟล์ได้';
        header("Location: price_import.php");
        exit();
    }

    // เริ่ม Transaction (ถ้า Error จะ Rollback ไม่ให้ข้อมูลพัง)
    $conn->begin_transaction();

    try {
        $row = 0;
        $success_count = 0;
        $user_id = $_SESSION['user_id'];

        // เตรียม Statement เพื่อความเร็วและความปลอดภัย
        // 1. SQL ปิดราคาเก่า
        $sql_close = "UPDATE product_prices SET end_date = ? WHERE wood_code = ? AND end_date = '9999-12-31'";
        $stmt_close = $conn->prepare($sql_close);

        // 2. SQL เพิ่มราคาใหม่
        $sql_insert = "INSERT INTO product_prices (wood_code, unit_price, start_date, end_date, created_by) VALUES (?, ?, ?, '9999-12-31', ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row++;
            // ข้ามหัวตาราง (Header)
            if ($row == 1) continue; 

            // Map Column ตาม Excel Image (CSV Index เริ่มที่ 0)
            // 0=wood_code, 1=mil, 2=width, 3=length, 4=UnitPrice, 5=Start_date
            $wood_code = trim($data[0]);
            $unit_price = floatval($data[4]);
            $raw_date = trim($data[5]); // รูปแบบใน Excel อาจเป็น d/m/Y หรือ Y-m-d

            // ข้ามถ้าไม่มี wood_code
            if (empty($wood_code)) continue;

            // แปลงวันที่ (รองรับ d/m/Y หรือ Y-m-d)
            // หมายเหตุ: ควร Save CSV ให้ date เป็น format Y-m-d จะชัวร์สุด
            // โค้ดนี้แปลงจาก d/m/Y -> Y-m-d
            $date_parts = explode('/', $raw_date);
            if (count($date_parts) == 3) {
                // ถ้ามาเป็น 1/1/2025
                $start_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
            } else {
                // ถ้ามาเป็น 2025-01-01 หรือ format อื่น
                $start_date = date('Y-m-d', strtotime($raw_date));
            }

            // --- LOGIC 1: คำนวณวันปิดราคาเก่า (New Start Date - 1 Day) ---
            $close_date = date('Y-m-d', strtotime($start_date . ' -1 day'));

            // --- LOGIC 2: ปิดราคาเก่าใน DB ---
            // "UPDATE end_date = เมื่อวาน WHERE code = สินค้านี้ AND end_date = 9999-12-31"
            $stmt_close->bind_param("ss", $close_date, $wood_code);
            $stmt_close->execute();

            // --- LOGIC 3: ใส่ราคาใหม่ ---
            $stmt_insert->bind_param("sdsi", $wood_code, $unit_price, $start_date, $user_id);
            $stmt_insert->execute();

            $success_count++;
        }

        fclose($handle);
        $conn->commit(); // ยืนยันการบันทึก

        $_SESSION['msg_status'] = 'success';
        $_SESSION['msg_text'] = "นำเข้าสำเร็จ! อัปเดตราคา $success_count รายการ";

    } catch (Exception $e) {
        $conn->rollback(); // ยกเลิกทั้งหมดถ้ามี Error
        $_SESSION['msg_status'] = 'error';
        $_SESSION['msg_text'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }

    header("Location: price_import.php");
    exit();
}
?>