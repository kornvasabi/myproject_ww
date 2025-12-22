<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

// รับค่า Action (save หรือ delete)
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($action == 'save' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- รับค่าจากฟอร์ม ---
    $id = $_POST['id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $type = $_POST['type'];
    $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : NULL; // ถ้าไม่เลือกให้เป็น NULL
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($id)) {
        // --- เพิ่มข้อมูลใหม่ (INSERT) ---
        $sql = "INSERT INTO servers (name, address, type, branch_id, is_active) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $name, $address, $type, $branch_id, $is_active);
        
        if ($stmt->execute()) {
            $_SESSION['msg_status'] = 'success';
            $_SESSION['msg_text'] = 'เพิ่ม Server ใหม่เรียบร้อยแล้ว';
        } else {
            $_SESSION['msg_status'] = 'error';
            $_SESSION['msg_text'] = 'เกิดข้อผิดพลาด: ' . $conn->error;
        }

    } else {
        // --- แก้ไขข้อมูล (UPDATE) ---
        $sql = "UPDATE servers SET name=?, address=?, type=?, branch_id=?, is_active=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiii", $name, $address, $type, $branch_id, $is_active, $id);

        if ($stmt->execute()) {
            $_SESSION['msg_status'] = 'success';
            $_SESSION['msg_text'] = 'อัปเดตข้อมูลเรียบร้อยแล้ว';
        } else {
            $_SESSION['msg_status'] = 'error';
            $_SESSION['msg_text'] = 'เกิดข้อผิดพลาด: ' . $conn->error;
        }
    }

    header("Location: server_list.php");
    exit();

} elseif ($action == 'delete' && isset($_GET['id'])) {
    // --- ลบข้อมูล (DELETE) ---
    $id = intval($_GET['id']);
    
    $sql = "DELETE FROM servers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['msg_status'] = 'success';
        $_SESSION['msg_text'] = 'ลบข้อมูลเรียบร้อยแล้ว';
    } else {
        $_SESSION['msg_status'] = 'error';
        $_SESSION['msg_text'] = 'ลบไม่ได้ เกิดข้อผิดพลาด';
    }

    header("Location: server_list.php");
    exit();

} else {
    // เข้ามาแบบงงๆ ส่งกลับไปหน้า List
    header("Location: server_list.php");
    exit();
}
?>