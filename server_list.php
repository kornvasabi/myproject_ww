<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

// ตรวจสิทธิ์หน้านี้
checkAccess($conn, basename($_SERVER['PHP_SELF']));

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. ดึงข้อมูล Servers
$sql = "SELECT s.*, b.branch_name 
        FROM servers s 
        LEFT JOIN branches b ON s.branch_id = b.id 
        ORDER BY s.id DESC";
$result = $conn->query($sql);

// 2. ดึงข้อมูล Branches มาเตรียมไว้ใส่ใน Dropdown ของ Modal
$branches_opt = [];
$sql_b = "SELECT * FROM branches ORDER BY branch_name ASC";
$res_b = $conn->query($sql_b);
while($row_b = $res_b->fetch_assoc()) {
    $branches_opt[] = $row_b;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>จัดการข้อมูล Server</title>
    <link href="/myproject_ww/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">จัดการข้อมูล Server</h1>
                        <button type="button" class="btn btn-primary shadow-sm" onclick="openModal('add')">
                            <i class="fas fa-plus fa-sm text-white-50"></i> เพิ่ม Server ใหม่
                        </button>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">รายการ Server ทั้งหมด</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>ชื่อ Server</th>
                                            <th>ที่อยู่ (IP/URL)</th>
                                            <th>ประเภท</th>
                                            <th>สาขา</th>
                                            <th>สถานะ</th>
                                            <th>Active</th>
                                            <th width="150">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()) { ?>
                                        <tr>
                                            <td><?php echo $row['id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['address']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo ($row['type'] == 'ip') ? 'info' : 'warning'; ?>">
                                                    <?php echo strtoupper($row['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['branch_name'] ? $row['branch_name'] : '- ไม่ระบุ -'; ?></td>
                                            <td>
                                                <span class="badge badge-secondary"><?php echo $row['status']; ?></span>
                                                <small class="d-block text-muted"><?php echo $row['last_check']; ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php echo ($row['is_active']) ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-secondary"></i>'; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-warning btn-sm" 
                                                    onclick='openModal("edit", <?php echo json_encode($row); ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm btn-delete" data-id="<?php echo $row['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
            <?php include($root_path . '/includes/footer.php'); ?>
        </div>
    </div>

    <div class="modal fade" id="serverModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document"> <div class="modal-content">
                <form action="server_save.php" method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalTitle">เพิ่ม Server ใหม่</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="modal_id">

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>ชื่อ Server <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="modal_name" class="form-control" required placeholder="เช่น Server สาขา 1">
                            </div>
                            <div class="form-group col-md-6">
                                <label>ประเภทการเชื่อมต่อ</label>
                                <select name="type" id="modal_type" class="form-control">
                                    <option value="ip">IP Address</option>
                                    <option value="url">URL / Domain</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>ที่อยู่ (IP หรือ URL) <span class="text-danger">*</span></label>
                            <input type="text" name="address" id="modal_address" class="form-control" required placeholder="เช่น 192.168.1.100 หรือ https://api.mysite.com">
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>สาขาที่สังกัด</label>
                                <select name="branch_id" id="modal_branch_id" class="form-control">
                                    <option value="">-- ไม่ระบุ / ส่วนกลาง --</option>
                                    <?php foreach($branches_opt as $b) { ?>
                                        <option value="<?php echo $b['id']; ?>"><?php echo $b['branch_name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>สถานะการใช้งาน</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="modal_is_active" name="is_active" value="1" checked>
                                    <label class="custom-control-label" for="modal_is_active">เปิดใช้งาน (Active)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include('includes/logout_modal.php'); ?>

    <script src="/myproject_ww/vendor/jquery/jquery.min.js"></script>
    <script src="/myproject_ww/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/myproject_ww/js/sb-admin-2.min.js"></script>
    <script src="/myproject_ww/vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="/myproject_ww/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable();

            // Script ลบข้อมูล (แก้ไขให้รองรับ DataTable Pagination)
            $('#dataTable tbody').on('click', '.btn-delete', function() {
                // ตรวจสอบก่อนว่าปุ่มไม่ได้ถูก disable อยู่
                if ($(this).is(':disabled')) {
                    return; 
                }
                
                var id = $(this).data('id');
                Swal.fire({
                    title: 'ยืนยันการลบ?',
                    text: "คุณต้องการลบ Server ID: " + id + " ใช่หรือไม่?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ลบเลย!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'server_save.php?action=delete&id=' + id;
                    }
                });
            });
        });

        // ฟังก์ชันเปิด Modal (ใช้ร่วมกันทั้ง เพิ่ม และ แก้ไข)
        function openModal(mode, data = null) {
            if (mode === 'add') {
                // โหมดเพิ่ม: ล้างค่าฟอร์ม
                $('#modalTitle').text('เพิ่ม Server ใหม่');
                $('#modal_id').val(''); // ID ว่างแปลว่า Insert
                $('#modal_name').val('');
                $('#modal_address').val('');
                $('#modal_type').val('ip');
                $('#modal_branch_id').val('');
                $('#modal_is_active').prop('checked', true); // Default Active
            } else {
                // โหมดแก้ไข: เอาข้อมูลจาก JSON มาใส่
                $('#modalTitle').text('แก้ไขข้อมูล Server (ID: ' + data.id + ')');
                $('#modal_id').val(data.id);
                $('#modal_name').val(data.name);
                $('#modal_address').val(data.address);
                $('#modal_type').val(data.type);
                $('#modal_branch_id').val(data.branch_id);
                
                // เช็คสถานะ Active (Database เก็บ 1/0 ต้องแปลงเป็น true/false)
                if (data.is_active == 1) {
                    $('#modal_is_active').prop('checked', true);
                } else {
                    $('#modal_is_active').prop('checked', false);
                }
            }
            $('#serverModal').modal('show');
        }
    </script>
    
    <?php if (isset($_SESSION['msg_status'])) { ?>
        <script>
            Swal.fire({
                icon: '<?php echo $_SESSION['msg_status']; ?>',
                title: '<?php echo $_SESSION['msg_text']; ?>',
                showConfirmButton: false,
                timer: 1500
            });
        </script>
        <?php unset($_SESSION['msg_status']); unset($_SESSION['msg_text']); ?>
    <?php } ?>
</body>
</html>