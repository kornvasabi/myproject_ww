<?php
// db.php
$host = "localhost";
$port = 3307;   // พอร์ต MySQL มาตรฐาน (เปลี่ยนได้ถ้าใช้พอร์ตอื่น)
$user = "root"; // เปลี่ยนตามของคุณ
$pass = "p@ssword";     // เปลี่ยนตามของคุณ
$dbname = "sb_admin_db";

$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่าภาษาไทย
$conn->set_charset("utf8mb4");
?>