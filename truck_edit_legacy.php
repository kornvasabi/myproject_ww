<?php
session_start();
require_once 'includes/auth.php'; // เช็คสิทธิ์ก่อน
require_once 'db.php'; // เรียกใช้ db เพื่อดึงข้อมูลสาขา
checkAccess($conn, basename($_SERVER['PHP_SELF'])); // เปิดใช้ถ้ากำหนดสิทธิ์แล้ว

// [ปรับปรุง 2] ดึงรายชื่อสาขามาแสดงใน Dropdown
$sql_branch = "SELECT * FROM branches WHERE api_url IS NOT NULL AND api_url != ''";
$result_branch = $conn->query($sql_branch);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    
    <!-- base href="../" --> 

    <title>แก้ไขเวลาชั่ง (ระบบเก่า) - SB Admin 2</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
</head>

<body id="page-top">
    <div id="wrapper">
        
        <?php include('includes/sidebar.php'); ?>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                
                <?php include('includes/topbar.php'); ?>
                
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">แก้ไขเวลาเข้า-ออก (เชื่อมต่อระบบเก่า 5.6)</h1>

                    <?php if (isset($_SESSION['api_result'])) { ?>
                        <div class="alert alert-<?php echo $_SESSION['api_status']; ?> alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['api_result']; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['api_result']); unset($_SESSION['api_status']); ?>
                    <?php } ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">แบบฟอร์มแก้ไขข้อมูล</h6>
                        </div>
                        <div class="card-body">
                            <form action="truck_api_action.php" method="POST">
                                
                                <div class="form-group row">
                                    <div class="col-sm-12 mb-3">
                                        <label class="text-primary font-weight-bold">เลือกสาขา (Server ปลายทาง)</label>
                                        <select name="branch_id" class="form-control" required>
                                            <option value="">-- กรุณาเลือกสาขา --</option>
                                            <?php 
                                            if ($result_branch && $result_branch->num_rows > 0) {
                                                while($branch = $result_branch->fetch_assoc()) { 
                                                    // จำค่าล่าสุดที่เลือกไว้
                                                    $selected = (isset($_SESSION['last_branch_id']) && $_SESSION['last_branch_id'] == $branch['id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $branch['id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo $branch['branch_name']; ?> 
                                                </option>
                                            <?php 
                                                } 
                                            } 
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <hr>

                                <div class="form-group row">
                                    <div class="col-sm-4 mb-3 mb-sm-0">
                                        <label>Transaction ID (ID ของตาราง)</label>
                                        <input type="number" name="id" class="form-control" required placeholder="ระบุ ID ที่ต้องการแก้">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-sm-6 mb-3 mb-sm-0">
                                        <label>เวลาเข้า (In Date Time)</label>
                                        <input type="datetime-local" name="in_date_time" class="form-control" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label>เวลาออก (Out Date Time)</label>
                                        <input type="datetime-local" name="out_date_time" class="form-control">
                                        <small class="text-muted">เว้นว่างได้หากรถยังไม่ออก</small>
                                    </div>
                                </div>
                                <hr>
                                <button type="submit" class="btn btn-warning btn-icon-split">
                                    <span class="icon text-white-50"><i class="fas fa-satellite-dish"></i></span>
                                    <span class="text">ส่งข้อมูลไป Server เก่า</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="sticky-footer bg-white">
                <div class="container my-auto"><div class="copyright text-center my-auto"><span>Copyright &copy; Your System 2025</span></div></div>
            </footer>
        </div>
    </div>
    
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
</body>
</html>