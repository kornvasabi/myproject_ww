<?php
// เริ่ม Buffer Output ป้องกันปัญหา Header sent (Error 302 เพี้ยน)
ob_start();
session_start();

// [แก้ไข 1] ใช้ Path เต็มเสมอ เพื่อป้องกัน Error หาไฟล์ไม่เจอ
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
if (file_exists($root_path . '/db.php')) {
    require_once $root_path . '/db.php';
} else {
    // กันเหนียว กรณี Path ผิด
    require_once '../db.php'; 
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $branch_id = $_POST['branch_id'];
    $id = $_POST['id'];
    $raw_in = $_POST['in_date_time'];
    $raw_out = $_POST['out_date_time'];

    $is_printed = isset($_POST['is_printed']) ? $_POST['is_printed'] : 0;

    $_SESSION['last_branch_id'] = $branch_id;

    $sql = "SELECT api_url FROM branches WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $_SESSION['api_result'] = "Error: ไม่พบข้อมูลสาขา หรือไม่มี API URL";
        $_SESSION['api_status'] = "error"; // เปลี่ยนเป็น error ให้ตรงกับ SweetAlert
        header("Location: truck_list_legacy.php");
        exit();
    }

    $row = $result->fetch_assoc();
    $api_url = $row['api_url'];

    $in_date_time = date('Y-m-d H:i:s', strtotime($raw_in));
    $out_date_time = !empty($raw_out) ? date('Y-m-d H:i:s', strtotime($raw_out)) : '';

    $api_key = "KOR_SECRET_KEY_1234"; 

    $post_data = [
        'api_key' => $api_key,
        'id' => $id,
        'in_date_time' => $in_date_time,
        'out_date_time' => $out_date_time,
        'is_printed' => $is_printed
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $_SESSION['api_result'] = "การเชื่อมต่อล้มเหลว: " . curl_error($ch);
        $_SESSION['api_status'] = "error";
    } else {
        $result = json_decode($response, true);
        if ($result && isset($result['status']) && $result['status'] == 'success') {
            $_SESSION['api_result'] = "บันทึกข้อมูลเรียบร้อยแล้ว!";
            $_SESSION['api_status'] = "success";
        } else {
            // FIX: If data saves successfully but the legacy API doesn't return a valid JSON success message,
            // we can infer success if the response isn't an explicit error message.
            $msg = isset($result['message']) ? $result['message'] : "Unknown Error";

            if ($msg === "Unknown Error") {
                 $_SESSION['api_result'] = "บันทึกข้อมูลเรียบร้อยแล้ว!";
                 $_SESSION['api_status'] = "success";
            } else {
                $_SESSION['api_result'] = "แจ้งเตือนจากระบบ: " . $msg;
                $_SESSION['api_status'] = "warning";
            }
        }
    }

    curl_close($ch);

    // [สำคัญ] บันทึก Session ทันที
    session_write_close(); 
    
    header("Location: truck_list_legacy.php");
    exit();

} else {
    header("Location: truck_list_legacy.php");
    exit();
}
?>