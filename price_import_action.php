<?php
// เริ่มต้นใช้งาน Session (ต้องมีในทุกไฟล์ที่ต้องการรับ/ส่งค่า Session ข้ามหน้า)
session_start();

// กำหนด path รากของเว็บไซต์ (Document Root) เพื่อให้การ include ไฟล์ไม่ผิดพลาด ไม่ว่าจะรันจากโฟลเดอร์ไหน
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';

// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล ($conn)
require_once $root_path . '/db.php';

// เรียกใช้ไฟล์ตรวจสอบสิทธิ์การเข้าใช้งาน (Login Check)
require_once $root_path . '/includes/auth.php';

// ============================================================================
// MAIN LOGIC: ส่วนการทำงานหลัก
// ============================================================================

// ตรวจสอบว่ามีการเรียกหน้านี้แบบ POST (กดปุ่ม Submit) และมีไฟล์แนบมาชื่อ 'price_file'
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['price_file'])) {
    
    // รับ Path ชั่วคราวของไฟล์ที่อัปโหลดมา (ไฟล์จะถูกเก็บไว้ที่ Temp ของ Server ก่อน)
    $file = $_FILES['price_file']['tmp_name'];
    
    // สั่งเปิดไฟล์ CSV ในโหมดอ่านอย่างเดียว (r = Read)
    $handle = fopen($file, "r");
    
    // ตรวจสอบว่าเปิดไฟล์สำเร็จไหม (ถ้าไฟล์เสีย หรือ Permission ไม่ได้ จะเป็น FALSE)
    if ($handle === FALSE) {
        $_SESSION['msg_status'] = 'error'; // ตั้งค่าสถานะเป็น Error
        $_SESSION['msg_text'] = 'ไม่สามารถเปิดไฟล์ได้'; // ข้อความแจ้งเตือน
        header("Location: price_import.php"); // ส่งกลับไปหน้าฟอร์ม
        exit(); // จบการทำงานทันที
    }

    // =========================================================
    // STEP 1: ตรวจสอบความถูกต้อง (Validation Phase)
    // เป้าหมาย: หาว่ามี "รหัสสินค้าซ้ำกันเอง" ในไฟล์ที่อัปโหลดมาหรือไม่
    // =========================================================
    
    $all_wood_codes = []; // สร้าง Array ว่าง เตรียมไว้เก็บรหัสสินค้าทั้งหมดเพื่อนำมานับ
    $row_count = 0; // ตัวแปรนับจำนวนบรรทัด

    // วนลูปอ่านไฟล์ทีละบรรทัดด้วยฟังก์ชัน fgetcsv
    // 1000 = ความยาวสูงสุดต่อบรรทัด, "," = ตัวคั่นคอลัมน์, '"' = ตัวครอบข้อความ, "\\" = ตัว escape
    while (($data = fgetcsv($handle, 1000, ",", '"', "\\")) !== FALSE) {
        $row_count++; // นับบรรทัดเพิ่มทีละ 1
        
        if ($row_count == 1) continue; // ถ้าเป็นบรรทัดแรก (Header หัวตาราง) ให้ข้ามไป ไม่ต้องทำอะไร

        // ดึงข้อมูลคอลัมน์ที่ 0 (wood_code) มาตัดช่องว่างซ้ายขวา (Trim)
        $wood_code = trim($data[0]);

        // ถ้ามีรหัสสินค้า (ไม่เป็นค่าว่าง) ให้เก็บใส่ Array รวมไว้
        if (!empty($wood_code)) {
            $all_wood_codes[] = $wood_code;
        }
    }

    // ใช้ฟังก์ชัน array_count_values เพื่อนับจำนวนความถี่ของแต่ละรหัส
    // ผลลัพธ์จะเป็น Array เช่น ['A01' => 1, 'A02' => 2] (A02 ซ้ำ 2 ครั้ง)
    $code_counts = array_count_values($all_wood_codes);
    $duplicate_codes = []; // เตรียม Array เก็บรายชื่อตัวที่ซ้ำ

    // // print_r($code_counts); exit; // (บรรทัดนี้ถูก comment ไว้ ใช้สำหรับ Debug ดูค่า)

    // วนลูปตรวจสอบผลการนับ
    foreach ($code_counts as $code => $count) {
        // ถ้าจำนวนมากกว่า 1 แสดงว่าซ้ำ
        if ($count > 1) {
            $duplicate_codes[] = $code; // เก็บชื่อรหัสที่ซ้ำไว้
        }
    }

    // ** ถ้าพบตัวซ้ำ (Array $duplicate_codes ไม่ว่าง) ให้หยุดการทำงานทันที **
    if (!empty($duplicate_codes)) {
        fclose($handle); // ปิดไฟล์ CSV เพื่อคืน Resource
        
        // แปลง Array รายชื่อตัวซ้ำ เป็นข้อความ String เรียงกันด้วย comma (เช่น "1001, 1002")
        $error_list = implode(', ', $duplicate_codes);
        
        // ตั้งค่าข้อความแจ้งเตือน Error เพื่อส่งกลับไปแสดงผล
        $_SESSION['msg_status'] = 'error';
        $_SESSION['msg_text'] = "เกิดข้อผิดพลาด! พบรหัสสินค้าซ้ำในไฟล์เดียวกัน: ($error_list) กรุณาตรวจสอบและแก้ไขไฟล์ก่อนนำเข้า";
        
        header("Location: price_import.php"); // ดีดกลับไปหน้าเดิม
        exit(); // จบการทำงาน
    }
    
    // =========================================================
    // STEP 1.5: ตรวจสอบข้อมูลซ้ำซ้อนกับ Database (Overlap Check)
    // =========================================================
    
    $db_conflict_errors = [];

    // เตรียม SQL: เช็คว่ามีข้อมูลเดิม ที่วันที่ start_date "มากกว่าหรือเท่ากับ" วันที่ใหม่หรือไม่
    // ถ้ามี แสดงว่าเรากำลังจะย้อนเวลา หรือใส่ข้อมูลซ้ำ ซึ่งไม่ถูกต้อง
    $sql_check_db = "SELECT start_date FROM product_prices WHERE wood_code = ? AND start_date >= ? LIMIT 1";
    $stmt_check_db = $conn->prepare($sql_check_db);

    foreach ($all_csv_data as $item) {
        $code = $item['code'];
        $raw_date = $item['raw_date'];

        // แปลงวันที่ให้เป็น Y-m-d เพื่อเทียบกับ Database
        $date_parts = explode('/', $raw_date);
        if (count($date_parts) == 3) {
            $check_date = $date_parts[2] . '-' . str_pad($date_parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
        } else {
            $check_date = date('Y-m-d', strtotime($raw_date));
        }

        // ยิง Query เช็ค
        $stmt_check_db->bind_param("ss", $code, $check_date);
        $stmt_check_db->execute();
        $stmt_check_db->store_result();

        // ถ้าเจอข้อมูล (Rows > 0) แสดงว่าซ้ำซ้อน
        if ($stmt_check_db->num_rows > 0) {
            $db_conflict_errors[] = "$code (วันที่ $check_date)";
        }
    }

    // ถ้ามีข้อขัดแย้งกับ DB ให้หยุดและแจ้งเตือน
    if (!empty($db_conflict_errors)) {
        fclose($handle);
        
        // ตัดให้เหลือแค่ 5 รายการแรกพองาม ถ้าเยอะเกินเดี๋ยวยาว
        $show_limit = array_slice($db_conflict_errors, 0, 5);
        $error_msg = implode(', ', $show_limit);
        if (count($db_conflict_errors) > 5) $error_msg .= " และอื่นๆ...";

        $_SESSION['msg_status'] = 'error';
        $_SESSION['msg_text'] = "นำเข้าล้มเหลว! พบวันที่ซ้ำซ้อนหรือย้อนหลังข้อมูลเดิมในระบบ: [ $error_msg ] กรุณาใช้วันที่ที่ใหม่กว่าปัจจุบัน";
        header("Location: price_import.php");
        exit();
    }
    // =========================================================
    // STEP 2: บันทึกข้อมูลลงฐานข้อมูล (Processing Phase)
    // =========================================================
    
    // สำคัญมาก: รีเซ็ตเคอร์เซอร์ตัวอ่านไฟล์กลับไปที่บรรทัดแรกสุด
    // (เพราะใน Step 1 เราอ่านจนจบไฟล์ไปแล้ว ถ้าไม่ Rewind จะอ่านต่อไม่ได้)
    rewind($handle);

    // เริ่ม Transaction (ระบบความปลอดภัยข้อมูล: ถ้าพังกลางทาง ข้อมูลจะไม่ถูกบันทึกเลยสักบรรทัด)
    $conn->begin_transaction(); 

    try {
        $row = 0; // รีเซ็ตตัวนับบรรทัดสำหรับรอบบันทึกจริง
        $success_count = 0; // ตัวนับจำนวนรายการที่บันทึกสำเร็จ
        $user_id = $_SESSION['user_id']; // ดึง ID ผู้ใช้งานปัจจุบันจาก Session

        // --- เตรียมคำสั่ง SQL (Prepared Statements) เพื่อความเร็วและความปลอดภัย ---
        
        // Query 1: อัปเดตราคาเก่าให้ปิดตัวลง 
        // Logic: ค้นหา wood_code นี้ ที่ end_date ยังเป็น '9999-12-31' (คือราคาปัจจุบัน) แล้วแก้วันจบเป็นวันที่เรากำหนด
        $sql_close = "UPDATE product_prices SET end_date = ? WHERE wood_code = ? AND end_date = '9999-12-31'";
        $stmt_close = $conn->prepare($sql_close);

        // Query 2: เพิ่มราคาใหม่
        // Logic: เพิ่ม wood_code, ราคา, วันเริ่ม และตั้งวันจบเป็น '9999-12-31' (ใช้ยาวตลอดไป)
        $sql_insert = "INSERT INTO product_prices (wood_code, unit_price, start_date, end_date, created_by) VALUES (?, ?, ?, '9999-12-31', ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        // วนลูปอ่านไฟล์ CSV อีกครั้ง เพื่อทำการบันทึกจริง
        while (($data = fgetcsv($handle, 1000, ",", '"', "\\")) !== FALSE) {
            $row++; // เพิ่มตัวนับบรรทัด
            if ($row == 1) continue; // ข้าม Header (บรรทัดแรก) อีกรอบ

            // ดึงข้อมูลและทำความสะอาด (Trim)
            $wood_code = trim($data[0]);    // คอลัมน์ 0: รหัสสินค้า
            $unit_price = floatval($data[1]); // คอลัมน์ 1: ราคา (แปลงเป็นทศนิยม)
            $raw_date = trim($data[2]);     // คอลัมน์ 2: วันที่เริ่ม (ข้อความ)

            // ถ้ารหัสสินค้าว่างเปล่า ให้ข้ามบรรทัดนี้ไป
            if (empty($wood_code)) continue;

            // --- แปลงรูปแบบวันที่ (Date Parsing) ---
            $date_parts = explode('/', $raw_date); // ลองแยกวันที่ด้วยเครื่องหมาย /
            
            if (count($date_parts) == 3) {
                // กรณีมาเป็น d/m/Y (เช่น 30/01/2025) แบบ Excel ไทย
                // แปลงเป็น Y-m-d (2025-01-30) เพื่อให้ Database รู้จัก
                $start_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
            } else {
                // กรณีมาเป็นรูปแบบอื่น (เช่น Y-m-d อยู่แล้ว) ให้ PHP ช่วยแปลง
                $start_date = date('Y-m-d', strtotime($raw_date));
            }

            // Logic 1: คำนวณวันปิดราคาเก่า
            // สูตร: วันปิดของเก่า = วันเริ่มของใหม่ ลบไป 1 วัน
            $close_date = date('Y-m-d', strtotime($start_date . ' -1 day'));

            // Logic 2: สั่ง SQL ปิดราคาเก่า
            // "ss" หมายถึง String, String (Date, WoodCode)
            $stmt_close->bind_param("ss", $close_date, $wood_code);
            $stmt_close->execute(); // สั่งทำงาน

            // Logic 3: สั่ง SQL เพิ่มราคาใหม่
            // "sdsi" หมายถึง String(Code), Double(Price), String(Date), Integer(UserID)
            $stmt_insert->bind_param("sdsi", $wood_code, $unit_price, $start_date, $user_id);
            $stmt_insert->execute(); // สั่งทำงาน

            $success_count++; // นับจำนวนความสำเร็จเพิ่มขึ้น 1
        }

        fclose($handle); // ปิดไฟล์ CSV เมื่ออ่านจบ
        $conn->commit(); // ยืนยัน Transaction (บันทึกข้อมูลทั้งหมดลง Database ถาวร)

        // ตั้งค่าข้อความแจ้งเตือนความสำเร็จ
        $_SESSION['msg_status'] = 'success';
        $_SESSION['msg_text'] = "นำเข้าสำเร็จ! อัปเดตราคา $success_count รายการ";

    } catch (Exception $e) {
        // กรณีเกิด Error ในบล็อก try (เช่น SQL Error)
        $conn->rollback(); // ยกเลิกสิ่งที่ทำไปทั้งหมด (Undo) กลับคืนค่าเดิม
        fclose($handle); // ปิดไฟล์ (อย่าลืมปิด แม้จะ Error)
        
        // ตั้งค่าข้อความแจ้งเตือน Error
        $_SESSION['msg_status'] = 'error';
        $_SESSION['msg_text'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }

    // ส่งกลับไปหน้าจอหลัก (price_import.php) เพื่อแสดงผลลัพธ์
    header("Location: price_import.php");
    exit(); // จบการทำงานของสคริปต์
}
?>