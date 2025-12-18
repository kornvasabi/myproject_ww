<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // ใช้ Prepared Statement เพื่อป้องกัน SQL Injection
    // UPDATED: เพิ่มการดึงค่า group_id, branch_id, dept_id
    $sql = "SELECT id, username, password, fullname, group_id, branch_id, dept_id 
            FROM users 
            WHERE username = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        
        // ตรวจสอบ Password
        if (password_verify($password, $row['password'])) {
            // Login สำเร็จ
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['fullname'] = $row['fullname'];
            
            // --- ส่วนที่เพิ่มมาใหม่สำหรับระบบสิทธิ์ ---
            $_SESSION['group_id'] = $row['group_id'];   // สำคัญมาก! ใช้สำหรับสร้างเมนู Dynamic
            $_SESSION['branch_id'] = $row['branch_id']; // เก็บไว้เผื่อกรองข้อมูลตามสาขา
            $_SESSION['dept_id'] = $row['dept_id'];     // เก็บไว้เผื่อกรองข้อมูลตามแผนก
            // ------------------------------------

            header("Location: index.php");
        } else {
            // Password ผิด
            header("Location: login.php?error=1");
        }
    } else {
        // Username ไม่พบ
        header("Location: login.php?error=1");
    }
} else {
    header("Location: login.php");
}
?>