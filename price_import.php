<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

// ปิดการใช้งาน global loader สำหรับหน้านี้
$disable_global_loader = true;

// ดึงราคาปัจจุบันมาแสดง (ที่ end_date เป็น 9999-12-31)
$sql = "SELECT * FROM product_prices WHERE end_date = '9999-12-31' ORDER BY wood_code ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>นำเข้าราคา (Price Import)</title>
    <link href="/myproject_ww/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="/myproject_ww/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="/myproject_ww/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include($root_path . '/includes/sidebar.php'); ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include($root_path . '/includes/topbar.php'); ?>
                
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">จัดการราคาสินค้า (Price Master)</h1>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-primary text-white">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-file-upload"></i> นำเข้าไฟล์ราคา (CSV)</h6>
                        </div>
                        <div class="card-body">
                            <form action="price_import_action.php" method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>เลือกไฟล์ CSV (Columns: wood_code, mil, width, length, UnitPrice, Start_date)</label>
                                    <input type="file" name="price_file" class="form-control-file" accept=".csv" required>
                                    <small class="text-muted">โปรดบันทึก Excel เป็น .csv (UTF-8) ก่อนนำเข้า</small>
                                </div>
                                <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> อัปโหลดและประมวลผล</button>
                                <!-- ใส่ data-toggle เพื่อไม่ให้ global_loader ทำงาน (ไม่ต้องโชว์โหลดดิ้งตอนโหลดไฟล์ template) -->
                                <a href="price_template.csv" class="btn btn-secondary" download data-toggle="download"><i class="fas fa-download"></i> ดาวน์โหลด Template</a>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ราคาที่ใช้งานอยู่ปัจจุบัน (Active Price)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>รหัสสินค้า (Wood Code)</th>
                                            <th>ราคา (Unit Price)</th>
                                            <th>เริ่มใช้ (Start)</th>
                                            <th>สิ้นสุด (End)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($row = $result->fetch_assoc()) { ?>
                                        <tr>
                                            <td><?php echo $row['wood_code']; ?></td>
                                            <td><?php echo number_format($row['unit_price'], 2); ?></td>
                                            <td><?php echo $row['start_date']; ?></td>
                                            <td><span class="badge badge-success">ปัจจุบัน</span></td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
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
    <script src="/myproject_ww/vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="/myproject_ww/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable();
        });
    </script>
    
    <?php if (isset($_SESSION['msg_status'])) { ?>
        <script>
            Swal.fire({
                icon: '<?php echo $_SESSION['msg_status']; ?>',
                title: '<?php echo $_SESSION['msg_text']; ?>',
                showConfirmButton: true
            });
        </script>
        <?php unset($_SESSION['msg_status']); unset($_SESSION['msg_text']); ?>
    <?php } ?>
</body>
</html>