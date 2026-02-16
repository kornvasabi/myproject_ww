<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/includes/auth.php'; // ถ้ามี
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ดึงข้อมูลจาก Server เก่า</title>
    <link href="/myproject_ww/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="/myproject_ww/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include($root_path . '/includes/sidebar.php'); ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include($root_path . '/includes/topbar.php'); ?>
                <div class="container-fluid">
                    
                    <h1 class="h3 mb-4 text-gray-800">Sync Data from MySQL 5.6</h1>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-info text-white">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-server"></i> ตั้งค่าการดึงข้อมูล</h6>
                        </div>
                        <div class="card-body">
                            <form action="import_server_action.php" method="POST" id="syncForm">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>เลือกวันที่ต้องการดึงข้อมูล (จาก Server เก่า)</label>
                                        <input type="text" name="sync_date" class="form-control date-picker" required>
                                    </div>
                                    <div class="form-group col-md-8 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-icon-split btn-lg">
                                            <span class="icon text-white-50"><i class="fas fa-sync-alt"></i></span>
                                            <span class="text">เริ่มดึงข้อมูล (Start Sync)</span>
                                        </button>
                                    </div>
                                </div>
                                <hr>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> คำเตือน: ระบบจะดึงข้อมูลเฉพาะ <b>Barcode ที่ยังไม่มีในระบบปัจจุบัน</b> เท่านั้น (เพื่อป้องกันข้อมูลซ้ำ)
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
            <?php include($root_path . '/includes/footer.php'); ?>
        </div>
    </div>

    <script src="/myproject_ww/vendor/jquery/jquery.min.js"></script>
    <script src="/myproject_ww/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/myproject_ww/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(".date-picker").flatpickr({ dateFormat: "Y-m-d", defaultDate: "today" });

        $('#syncForm').on('submit', function() {
            let btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> กำลังเชื่อมต่อและดึงข้อมูล...');
        });
    </script>
    
    <?php if (isset($_SESSION['msg_status'])) { ?>
        <script>
            Swal.fire({
                icon: '<?php echo $_SESSION['msg_status']; ?>',
                title: '<?php echo $_SESSION['msg_title']; ?>',
                text: '<?php echo $_SESSION['msg_text']; ?>'
            });
        </script>
        <?php unset($_SESSION['msg_status'], $_SESSION['msg_title'], $_SESSION['msg_text']); ?>
    <?php } ?>
</body>
</html>