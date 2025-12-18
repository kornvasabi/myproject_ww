<?php
// includes/auth.php

function checkAccess($conn, $current_page) {
    // 1. เช็คว่า Login หรือยัง
    if (!isset($_SESSION['group_id'])) {
        header("Location: login.php");
        exit();
    }

    $group_id = $_SESSION['group_id'];

    // 2. ข้อยกเว้น: หน้า index.php และ logout.php ให้เข้าได้ทุกคนที่มี user
    if ($current_page == 'index.php' || $current_page == 'logout.php') {
        return true; 
    }

    // 3. เช็คสิทธิ์จาก Database
    $sql = "SELECT p.id FROM permissions p 
            JOIN menus m ON p.menu_id = m.id 
            WHERE p.group_id = ? AND m.link = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $group_id, $current_page);
    $stmt->execute();
    $result = $stmt->get_result();

    // 4. ถ้าไม่พบสิทธิ์ (Row = 0) ให้แสดงหน้า Error ของ Template
    if ($result->num_rows == 0) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
            <title>Access Denied - SB Admin 2</title>

            <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
            <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

            <link href="css/sb-admin-2.min.css" rel="stylesheet">
        </head>

        <body id="page-top" style="height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #f8f9fc;">

            <div class="container-fluid">

                <div class="text-center">
                    <div class="error mx-auto" data-text="403">403</div>
                    <p class="lead text-gray-800 mb-5">Access Denied</p>
                    <p class="text-gray-500 mb-0">คุณไม่มีสิทธิ์เข้าถึงหน้านี้ หรือหน้าเว็บนี้ไม่ได้ถูกกำหนดไว้ในระบบ</p>
                    <br>
                    <a href="index.php" class="btn btn-primary btn-icon-split">
                        <span class="icon text-white-50">
                            <i class="fas fa-arrow-left"></i>
                        </span>
                        <span class="text">กลับไปหน้า Dashboard</span>
                    </a>
                </div>

            </div>
            <script src="vendor/jquery/jquery.min.js"></script>
            <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
            <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
            <script src="js/sb-admin-2.min.js"></script>

        </body>
        </html>
        <?php
        exit(); // จบการทำงานทันที ไม่ให้โหลดเนื้อหาหน้าเว็บต่อ
    }
}
?>