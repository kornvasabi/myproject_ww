<?php
session_start();
require_once 'db.php';
require_once 'includes/auth.php'; // <--- เพิ่มบรรทัดนี้ครับ!


// เช็คว่า Login หรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// รับค่า Action (add, edit, delete)
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action == 'add') {
    // --- เพิ่มข้อมูล ---
    $username = $_POST['username'];
    $fullname = $_POST['fullname'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // เข้ารหัสรหัสผ่าน

    $sql = "INSERT INTO users (username, fullname, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $fullname, $password);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "เพิ่มข้อมูลสำเร็จ!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "เกิดข้อผิดพลาด: " . $conn->error;
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: user_list.php");

} elseif ($action == 'edit') {
    // --- แก้ไขข้อมูล ---
    $id = $_POST['id'];
    $fullname = $_POST['fullname'];
    $password = $_POST['password'];

    if (!empty($password)) {
        // กรณีเปลี่ยนรหัสผ่านด้วย
        $new_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET fullname = ?, password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $fullname, $new_password, $id);
    } else {
        // กรณีไม่เปลี่ยนรหัสผ่าน (อัปเดตแค่ชื่อ)
        $sql = "UPDATE users SET fullname = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $fullname, $id);
    }

    if ($stmt->execute()) {
        $_SESSION['msg'] = "แก้ไขข้อมูลสำเร็จ!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "เกิดข้อผิดพลาด!";
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: user_list.php");

} elseif ($action == 'delete') {
    // --- ลบข้อมูล ---
    $id = $_GET['id'];
    
    // ป้องกันไม่ให้ลบตัวเอง
    if ($id == $_SESSION['user_id']) {
        $_SESSION['msg'] = "คุณไม่สามารถลบ User ที่กำลัง Login อยู่ได้!";
        $_SESSION['msg_type'] = "warning";
        header("Location: user_list.php");
        exit();
    }

    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['msg'] = "ลบข้อมูลสำเร็จ!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "เกิดข้อผิดพลาด!";
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: user_list.php");
} else {
    header("Location: user_list.php");
}
?>