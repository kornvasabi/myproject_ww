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

    // =========================================================
    // STEP 1: สแกนหาค่าซ้ำในไฟล์ (Validation Phase)
    // =========================================================
    $all_wood_codes = []; // ตัวแปรเก็บรหัสสินค้าทั้งหมดในไฟล์
    $row_count = 0;

    while (($data = fgetcsv($handle, 1000, ",", '"', "\\"))/*fgetcsv($handle, 1000, ",")*/ !== FALSE) {
        $row_count++;
        if ($row_count == 1) continue; // ข้าม Header

        // ดึง wood_code (Column 0) มาเช็ค
        $wood_code = trim($data[0]);

        if (!empty($wood_code)) {
            $all_wood_codes[] = $wood_code;
        }
    }

    // นับจำนวนความถี่ของแต่ละ code (เช่น A01=>1, A02=>2)
    $code_counts = array_count_values($all_wood_codes);
    $duplicate_codes = [];

    // วนลูปหาตัวที่ซ้ำ (มีมากกว่า 1)
    foreach ($code_counts as $code => $count) {
        if ($count > 1) {
            $duplicate_codes[] = $code;
        }
    }

    // ** ถ้าเจอตัวซ้ำ ให้หยุดการทำงานทันที **
    if (!empty($duplicate_codes)) {
        fclose($handle); // ปิดไฟล์
        
        // แปลง Array เป็น String เพื่อแสดงผล (เช่น "1001, 1002")
        $error_list = implode(', ', $duplicate_codes);
        
        $_SESSION['msg_status'] = 'error';
        $_SESSION['msg_text'] = "เกิดข้อผิดพลาด! พบรหัสสินค้าซ้ำในไฟล์เดียวกัน: ($error_list) กรุณาตรวจสอบและแก้ไขไฟล์ก่อนนำเข้า";
        
        header("Location: price_import.php");
        exit();
    }

    // =========================================================
    // STEP 2: เริ่มบันทึกข้อมูล (Processing Phase)
    // =========================================================
    
    // รีเซ็ตตัวอ่านไฟล์กลับไปบรรทัดแรกใหม่ (เพราะตะกี้อ่านจนจบไฟล์แล้ว)
    rewind($handle);

    $conn->begin_transaction(); // เริ่ม Transaction

    try {
        $row = 0;
        $success_count = 0;
        $user_id = $_SESSION['user_id'];

        // Prepared Statements
        $sql_close = "UPDATE product_prices SET end_date = ? WHERE wood_code = ? AND end_date = '9999-12-31'";
        $stmt_close = $conn->prepare($sql_close);

        $sql_insert = "INSERT INTO product_prices (wood_code, unit_price, start_date, end_date, created_by) VALUES (?, ?, ?, '9999-12-31', ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        while (($data = fgetcsv($handle, 1000, ",", '"', "\\")/*fgetcsv($handle, 1000, ",")*/) !== FALSE) {
            $row++;
            if ($row == 1) continue; // ข้าม Header อีกรอบ

            $wood_code = trim($data[0]);
            $unit_price = floatval($data[4]);
            $raw_date = trim($data[5]);

            if (empty($wood_code)) continue;

            // แปลงวันที่
            $date_parts = explode('/', $raw_date);
            if (count($date_parts) == 3) {
                $start_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
            } else {
                $start_date = date('Y-m-d', strtotime($raw_date));
            }

            // Logic 1: วันที่ปิด = วันเริ่ม - 1
            $close_date = date('Y-m-d', strtotime($start_date . ' -1 day'));

            // Logic 2: ปิดราคาเก่า
            $stmt_close->bind_param("ss", $close_date, $wood_code);
            $stmt_close->execute();

            // Logic 3: เพิ่มราคาใหม่
            $stmt_insert->bind_param("sdsi", $wood_code, $unit_price, $start_date, $user_id);
            $stmt_insert->execute();

            $success_count++;
        }

        fclose($handle);
        $conn->commit();

        $_SESSION['msg_status'] = 'success';
        $_SESSION['msg_text'] = "นำเข้าสำเร็จ! อัปเดตราคา $success_count รายการ";

    } catch (Exception $e) {
        $conn->rollback();
        fclose($handle); // อย่าลืมปิดไฟล์ถ้า Error
        $_SESSION['msg_status'] = 'error';
        $_SESSION['msg_text'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }

    header("Location: price_import.php");
    exit();
}
?>