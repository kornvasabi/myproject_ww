<?php
// db_sync.php

// 1. เชื่อมต่อ Local (Project ปัจจุบัน)
$local_host = "localhost";
$local_port = 3307;
$local_user = "root";
$local_pass = "p@ssword";
$local_db   = "sb_admin_db";
$conn_local = new mysqli($local_host, $local_user, $local_pass, $local_db, $local_port);
$conn_local->set_charset("utf8");

// 2. เชื่อมต่อ Remote (Server เก่า MySQL 5.6)
$remote_host = "192.168.0.18"; // IP เครื่องเก่า
$remote_user = "connect_mt";
$remote_pass = "p@ssword";
$remote_db   = "woodworkbarcode";
$conn_remote = new mysqli($remote_host, $remote_user, $remote_pass, $remote_db);
$conn_remote->set_charset("utf8"); // หรือ tis620 ถ้าของเก่าเป็นภาษาไทยแบบเก่า

// เช็ค Error
if ($conn_local->connect_error) die("Local Connection Failed: " . $conn_local->connect_error);
if ($conn_remote->connect_error) die("Remote Connection Failed: " . $conn_remote->connect_error);
?>