<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // --- 1. ดึง Path จาก Database ---
    $sql_conf = "SELECT config_value FROM system_config WHERE config_key = 'upload_path'";
    $res_conf = $conn->query($sql_conf);
    $row_conf = $res_conf->fetch_assoc();
    
    // ถ้ายังไม่ได้ตั้งค่า ให้ใช้ค่า Default เดิมในโปรเจกต์
    if ($row_conf && !empty($row_conf['config_value'])) {
        $upload_base_path = $row_conf['config_value']; // D:/ww_storage/
    } else {
        // Fallback (กรณี DB หาย)
        $upload_base_path = $root_path . '/ww/uploads/'; 
    }

    // สร้างโฟลเดอร์ถ้ายังไม่มี
    if (!file_exists($upload_base_path . 'images/')) mkdir($upload_base_path . 'images/', 0777, true);
    if (!file_exists($upload_base_path . 'files/')) mkdir($upload_base_path . 'files/', 0777, true);

    // ----------------------------------------

    $id = $_POST['id'];
    $title = $_POST['title'];
    $type_id = $_POST['type_id'];
    $description = $_POST['description'];
    $user_id = $_SESSION['user_id'];
    
    $image_name = isset($_POST['old_image']) ? $_POST['old_image'] : null;
    $file_name = isset($_POST['old_file']) ? $_POST['old_file'] : null;

    // --- 2. อัปโหลดรูปภาพ ---
    if (isset($_FILES['upload_image']) && $_FILES['upload_image']['error'] == 0) {
        $ext = pathinfo($_FILES['upload_image']['name'], PATHINFO_EXTENSION);
        $new_name = 'img_' . time() . '_' . rand(100,999) . '.' . $ext;
        
        // ย้ายไป Path นอก
        $target = $upload_base_path . 'images/' . $new_name;
        
        if (move_uploaded_file($_FILES['upload_image']['tmp_name'], $target)) {
            $image_name = $new_name; 
        }
    }

    // --- 3. อัปโหลดเอกสาร ---
    if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == 0) {
        $ext = pathinfo($_FILES['upload_file']['name'], PATHINFO_EXTENSION);
        $new_name = 'doc_' . time() . '_' . rand(100,999) . '.' . $ext;
        
        // ย้ายไป Path นอก
        $target = $upload_base_path . 'files/' . $new_name;
        
        if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target)) {
            $file_name = $new_name;
        }
    }

    // --- 4. บันทึก (เหมือนเดิม) ---
    if (empty($id)) {
        $sql = "INSERT INTO issues (title, description, type_id, user_id, image_path, file_path) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiss", $title, $description, $type_id, $user_id, $image_name, $file_name);
        $msg = "เพิ่มรายการใหม่เรียบร้อย";
    } else {
        $sql = "UPDATE issues SET title=?, description=?, type_id=?, image_path=?, file_path=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissi", $title, $description, $type_id, $image_name, $file_name, $id);
        $msg = "แก้ไขรายการเรียบร้อย";
    }

    if ($stmt->execute()) {
        $_SESSION['msg_status'] = 'success';
        $_SESSION['msg_text'] = $msg;
    } else {
        $_SESSION['msg_status'] = 'error';
        $_SESSION['msg_text'] = 'Error: ' . $stmt->error;
    }
    
    header("Location: issue_list.php");
    exit();
}
?>