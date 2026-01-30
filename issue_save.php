<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    $id = $_POST['id'];
    $title = $_POST['title'];
    $type_id = $_POST['type_id'];
    $description = $_POST['description'];
    $user_id = $_SESSION['user_id'];
    
    // เตรียมตัวแปรเก็บชื่อไฟล์ (ใช้ค่าเดิมก่อน ถ้ามีการอัปโหลดใหม่ค่อยเปลี่ยน)
    $image_name = isset($_POST['old_image']) ? $_POST['old_image'] : null;
    $file_name = isset($_POST['old_file']) ? $_POST['old_file'] : null;

    // --- 1. จัดการอัปโหลดรูปภาพ ---
    if (isset($_FILES['upload_image']) && $_FILES['upload_image']['error'] == 0) {
        $ext = pathinfo($_FILES['upload_image']['name'], PATHINFO_EXTENSION);
        $new_name = 'img_' . time() . '_' . rand(100,999) . '.' . $ext; // ตั้งชื่อใหม่กันซ้ำ
        $target = $root_path . '/uploads/images/' . $new_name;
        
        if (move_uploaded_file($_FILES['upload_image']['tmp_name'], $target)) {
            $image_name = $new_name; // อัปเดตชื่อไฟล์ลง DB
        }
    }

    // --- 2. จัดการอัปโหลดเอกสาร ---
    if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == 0) {
        $ext = pathinfo($_FILES['upload_file']['name'], PATHINFO_EXTENSION);
        $new_name = 'doc_' . time() . '_' . rand(100,999) . '.' . $ext;
        $target = $root_path . '/uploads/files/' . $new_name;
        
        if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target)) {
            $file_name = $new_name;
        }
    }

    // --- 3. บันทึกลง Database ---
    if (empty($id)) {
        // INSERT
        $sql = "INSERT INTO issues (title, description, type_id, user_id, image_path, file_path) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiss", $title, $description, $type_id, $user_id, $image_name, $file_name);
        $msg = "เพิ่มรายการใหม่เรียบร้อย";
    } else {
        // UPDATE
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