<?php
session_start();
require_once 'db.php'; // เรียกใช้ DB

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. รับค่าจากฟอร์ม
    $branch_id = $_POST['branch_id'];
    $id = $_POST['id'];
    $raw_in = $_POST['in_date_time'];
    $raw_out = $_POST['out_date_time'];

    // เก็บค่าสาขาล่าสุดไว้ใน Session (User จะได้ไม่ต้องเลือกใหม่ทุกรอบ)
    $_SESSION['last_branch_id'] = $branch_id;

    // 2. ค้นหา API URL จากตาราง branches ตาม ID ที่เลือก
    $sql = "SELECT api_url FROM branches WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $_SESSION['api_result'] = "Error: ไม่พบข้อมูลสาขา หรือไม่มี API URL ในระบบ";
        $_SESSION['api_status'] = "danger";
        header("Location: truck_edit_legacy.php");
        exit();
    }

    $row = $result->fetch_assoc();
    $api_url = $row['api_url']; // ได้ URL ปลายทางแล้ว

    // 3. แปลง Format เวลา (HTML datetime-local มีตัว T แต่ MySQL ใช้ช่องว่าง)
    $in_date_time = date('Y-m-d H:i:s', strtotime($raw_in));
    $out_date_time = !empty($raw_out) ? date('Y-m-d H:i:s', strtotime($raw_out)) : '';

    // 4. เตรียมข้อมูลส่ง API
    $api_key = "KOR_SECRET_KEY_1234"; // ต้องตรงกับไฟล์ฝั่ง Server เก่า

    $post_data = [
        'api_key' => $api_key,
        'id' => $id,
        'in_date_time' => $in_date_time,
        'out_date_time' => $out_date_time
    ];

    // 5. เริ่มส่งข้อมูลด้วย cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // เปิดบรรทัดนี้ถ้าปลายทางเป็น HTTPS แล้ว Error SSL

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        // กรณีเชื่อมต่อไม่ได้เลย (เช่น เน็ตหลุด, IP ผิด)
        $error_msg = curl_error($ch);
        $_SESSION['api_result'] = "การเชื่อมต่อล้มเหลว (cURL Error): " . $error_msg;
        $_SESSION['api_status'] = "danger";
    } else {
        // กรณีเชื่อมต่อได้ อ่านผลลัพธ์ JSON
        $result = json_decode($response, true);

        if ($result && isset($result['status']) && $result['status'] == 'success') {
            $_SESSION['api_result'] = "สำเร็จ! อัปเดตข้อมูล ID: $id เรียบร้อยแล้ว";
            $_SESSION['api_status'] = "success";
        } else {
            // ปลายทางตอบกลับมา แต่แจ้ง Error
            $msg = isset($result['message']) ? $result['message'] : "Unknown Error (Raw: $response)";
            $_SESSION['api_result'] = "แจ้งเตือนจาก Server ปลายทาง: " . $msg;
            $_SESSION['api_status'] = "warning";
        }
    }

    curl_close($ch);
    header("Location: truck_edit_legacy.php");

} else {
    header("Location: truck_edit_legacy.php");
}
?>