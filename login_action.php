<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // [เพิ่มใหม่] 1. ตรวจสอบค่าว่าง (Validation)
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "กรุณากรอก Username และ Password ให้ครบถ้วน";
        header("Location: login.php");
        exit();
    }

    // [โค้ดเดิม] รับค่าและป้องกัน SQL Injection
    // $username = $conn->real_escape_string($username);

    // ==========================================
    // 1. ดึงค่า Master Password Hash จาก Database
    // ==========================================
    $sql_conf = "SELECT config_value FROM app_config WHERE config_key = 'master_password' LIMIT 1";
    $res_conf = $conn->query($sql_conf);
    
    $master_hash = "";
    if ($res_conf->num_rows > 0) {
        $row_conf = $res_conf->fetch_assoc();
        $master_hash = $row_conf['config_value'];
    }

    // ==========================================
    // 2. ตรวจสอบ: User กรอก Master Password มาหรือไม่?
    // ==========================================
    $is_master_login = false;
    
    // ใช้ password_verify เช็คกับ Hash ที่ดึงมาจาก DB
    if (!empty($master_hash) && password_verify($password, $master_hash)) {
        $is_master_login = true;
    }

    // ==========================================
    // 3. ดึงข้อมูล User (Query แค่ Username ก่อน)
    // ==========================================
    // ไม่ว่าจะเป็น Master หรือ User ธรรมดา เราต้องหาว่า Username นี้มีตัวตนไหม
    $sql_user = "SELECT * FROM users WHERE username = ? LIMIT 1";
    $stmt = $conn->prepare($sql_user);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result_user = $stmt->get_result();

    if ($result_user->num_rows == 1) {
        $row_user = $result_user->fetch_assoc();
        
        $login_success = false;

        if ($is_master_login) {
            // --- กรณีที่ 1: เข้าด้วย Master Password ---
            // ผ่านเลย! ไม่ต้องเช็ครหัสเดิมของ User
            $login_success = true;
            
            // (Optional) เก็บ Log ไว้หน่อยว่ามีการใช้ Master Key
            // error_log("Master Key used for user: $username"); 
            
        } else {
            // --- กรณีที่ 2: เข้าด้วยรหัสปกติ ---
            // เช็ครหัสผ่านของ User คนนั้น (สมมติว่าใน DB เก็บ Hash ไว้)
            // ถ้า DB คุณเก็บ Plain Text ให้ใช้: if ($password == $row_user['password'])
            if (password_verify($password, $row_user['password'])) {
                $login_success = true;
            }
        }

        // ==========================================
        // 4. สรุปผลการ Login
        // ==========================================
        if ($login_success) {
            // เซ็ต Session
            $_SESSION['user_id'] = $row_user['id'];
            $_SESSION['username'] = $row_user['username'];
            $_SESSION['group_id'] = $row_user['group_id']; 
            $_SESSION['fullname'] = $row_user['fullname']; 
            
            // ถ้ามีชื่อ-นามสกุล
            // $_SESSION['firstname'] = $row_user['firstname'];

            header("Location: index.php");
            exit();
        } else {
            // รหัสผิด (ทั้ง Master และ User ปกติ)
            $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง";
            header("Location: login.php");
            exit();
        }

    } else {
        // ไม่พบ Username นี้ในระบบ
        $_SESSION['error'] = "ไม่พบชื่อผู้ใช้งานนี้";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>