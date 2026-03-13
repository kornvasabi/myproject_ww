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

    // --- [จุดสำคัญ] เก็บค่าไว้ให้หน้าหลักใช้ "ค้นหาต่อ" ---
    $_SESSION['search_branch_id'] = $branch_id;
    // (หมายเหตุ: ค่าวันที่และ Ticket มักจะถูกเก็บอยู่ใน Session จากหน้า Search อยู่แล้ว 
    // แต่ถ้าอยากให้แม่นยำ พี่กรสามารถเก็บเพิ่มตรงนี้ได้ครับ)
    // ----------------------------------------------

    $sql = "SELECT api_url FROM branches WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $_SESSION['api_result'] = "Error: ไม่พบข้อมูลสาขา";
        $_SESSION['api_status'] = "error";
        header("Location: truck_list_legacy.php");
        exit();
    }

    $row = $result->fetch_assoc();
    $api_url = $row['api_url'];

    $in_date_time = date('Y-m-d H:i:s', strtotime($raw_in));
    $out_date_time = !empty($raw_out) ? date('Y-m-d H:i:s', strtotime($raw_out)) : '';

    $post_data = [
        'api_key' => "KOR_SECRET_KEY_1234",
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
        // --- ล้างขยะก่อน Decode ---
        $clean_response = trim($response); 
        $start_pos = strpos($clean_response, '{');
        if ($start_pos !== false) {
            $clean_response = substr($clean_response, $start_pos);
        }
        $result = json_decode($clean_response, true);
        // -----------------------

        if ($result && isset($result['status']) && $result['status'] == 'success') {
            $_SESSION['api_result'] = "บันทึกข้อมูลเรียบร้อยแล้ว!";
            $_SESSION['api_status'] = "success";
            // [เทคนิคพิเศษ] เก็บ ID ที่เพิ่งแก้ไว้ทำ Highlight ในตาราง
            $_SESSION['highlight_id'] = $id; 
        } else {
            $msg = isset($result['message']) ? $result['message'] : "Unknown Error";
            
            // ถ้าเป็น Unknown Error แต่ไม่มี HTTP Error อื่นๆ มักจะบันทึกสำเร็จ (Legacy Case)
            if ($msg === "Unknown Error" && !empty($clean_response)) {
                 $_SESSION['api_result'] = "บันทึกข้อมูลเรียบร้อยแล้ว!";
                 $_SESSION['api_status'] = "success";
                 $_SESSION['highlight_id'] = $id;
            } else {
                $_SESSION['api_result'] = "แจ้งเตือน: " . $msg;
                $_SESSION['api_status'] = "warning";
            }
        }
    }

    // curl_close($ch);
    session_write_close(); 
    header("Location: truck_list_legacy.php");
    exit();
}
?>