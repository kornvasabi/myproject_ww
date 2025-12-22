<?php
session_start();
require_once 'db.php';
require_once 'includes/auth.php'; // <--- เพิ่มบรรทัดนี้ครับ!

// ตรวจสิทธิ์หน้านี้
checkAccess($conn, basename($_SERVER['PHP_SELF']));

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ดึงข้อมูล User ทั้งหมด
$sql = "SELECT * FROM users ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>จัดการผู้ใช้งาน - SB Admin 2</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
</head>

<body id="page-top">
    <div id="wrapper">
        
        <?php include('includes/sidebar.php'); ?>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                
                <?php include('includes/topbar.php'); ?>

                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">จัดการข้อมูลผู้ใช้งาน</h1>
                        <button type="button" class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#addModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> เพิ่มผู้ใช้งานใหม่
                        </button>
                    </div>

                    <?php if (isset($_SESSION['msg'])) { ?>
                        <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['msg']; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
                    <?php } ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">รายชื่อผู้ใช้งานทั้งหมด</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ลำดับ</th>
                                            <th>Username</th>
                                            <th>ชื่อ-นามสกุล</th>
                                            <th>วันที่สร้าง</th>
                                            <th class="text-center" style="width: 150px;">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $i = 1;
                                        while ($row = $result->fetch_assoc()) { 
                                        ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                            <td><?php echo date("d/m/Y H:i", strtotime($row['created_at'])); ?></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-warning btn-sm btn-edit" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($row['username']); ?>"
                                                    data-fullname="<?php echo htmlspecialchars($row['fullname']); ?>"
                                                    data-toggle="modal" data-target="#editModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <a href="user_action.php?action=delete&id=<?php echo $row['id']; ?>" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('คุณต้องการลบผู้ใช้งาน <?php echo $row['username']; ?> ใช่หรือไม่?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            
            <?php include('includes/footer.php'); ?>
        </div>
    </div>

    <div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="user_action.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addModalLabel">เพิ่มผู้ใช้งานใหม่</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required placeholder="ตั้งชื่อผู้ใช้งาน">
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required placeholder="ตั้งรหัสผ่าน">
                        </div>
                        <div class="form-group">
                            <label>ชื่อ-นามสกุล</label>
                            <input type="text" name="fullname" class="form-control" required placeholder="ระบุชื่อจริง">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="user_action.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">แก้ไขข้อมูลผู้ใช้งาน</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id"> <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>ชื่อ-นามสกุล</label>
                            <input type="text" name="fullname" id="edit_fullname" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>เปลี่ยนรหัสผ่าน (ถ้าไม่เปลี่ยนให้เว้นว่าง)</label>
                            <input type="password" name="password" class="form-control" placeholder="ระบุรหัสผ่านใหม่ถ้าต้องการเปลี่ยน">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include('includes/logout_modal.php'); ?>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>

    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            // 1. เริ่มทำงาน DataTables (ค้นหา, เรียงลำดับ, แบ่งหน้า)
            $('#dataTable').DataTable({
                "language": {
                    "search": "ค้นหา:",
                    "lengthMenu": "แสดง _MENU_ รายการ",
                    "info": "แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ",
                    "paginate": {
                        "first": "หน้าแรก",
                        "last": "หน้าสุดท้าย",
                        "next": "ถัดไป",
                        "previous": "ก่อนหน้า"
                    },
                    "zeroRecords": "ไม่พบข้อมูลที่ค้นหา"
                }
            });

            // 2. Script สำหรับปุ่มแก้ไข (ดึงข้อมูลจากปุ่มมาใส่ใน Modal)
            $('.btn-edit').on('click', function() {
                var id = $(this).data('id');
                var username = $(this).data('username');
                var fullname = $(this).data('fullname');

                // เอาค่าไปใส่ใน Input ของ Modal แก้ไข
                $('#edit_id').val(id);
                $('#edit_username').val(username);
                $('#edit_fullname').val(fullname);
            });
        });
    </script>
</body>
</html>