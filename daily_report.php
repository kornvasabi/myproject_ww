<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

checkAccess($conn, basename($_SERVER['PHP_SELF'])); // เปิดใช้ถ้ากำหนดสิทธิ์แล้ว

// ==========================================
// 1. ตั้งค่ารายชื่อระบบ (แก้ไขตรงนี้ได้เลย)
// ==========================================
$system_options = [
    'Hardware/Device' => 'primary',   // สีน้ำเงิน
    'ระบบบัญชี AX/ERP' => 'info',     // สีฟ้า
    'Server/Database' => 'dark',      // สีดำ
    'ระบบ QR-Code' => 'success',      // สีเขียว
    'ระบบ Truck83' => 'warning',      // สีเหลือง
    'ระบบค่าแรง4' => 'danger',      // สีแดง
    'ระบบตาชั่งเล็ก' => 'primary',   // สีน้ำเงิน
    'งานเอกสาร/ทั่วไป' => 'secondary' // สีเทา
];

// --- 2. ตรวจสอบสิทธิ์ ---
$is_admin = (isset($_SESSION['group_id']) && $_SESSION['group_id'] == 1);
$current_user_id = $_SESSION['user_id'];

// --- 3. ส่วนจัดการบันทึกข้อมูล (Save/Delete) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'save') {
        $id = $_POST['id'];
        $report_date = $_POST['report_date'];
        $time_range = $_POST['time_range'];
        $system_name = $_POST['system_name']; // [เพิ่ม] รับค่าระบบ
        $task_detail = $_POST['task_detail'];
        $work_result = $_POST['work_result'];
        $remark = $_POST['remark'];
        $user_id_save = $_SESSION['user_id']; 

        if (empty($id)) {
            // INSERT (เพิ่ม system_name)
            $stmt = $conn->prepare("INSERT INTO daily_reports (user_id, report_date, time_range, system_name, task_detail, work_result, remark) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $user_id_save, $report_date, $time_range, $system_name, $task_detail, $work_result, $remark);
            $msg = "เพิ่มรายงานเรียบร้อยแล้ว";
        } else {
            // Check Owner
            $sql_check = "SELECT user_id FROM daily_reports WHERE id = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("i", $id);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result();
            $row_check = $res_check->fetch_assoc();
            
            if (!$is_admin && $row_check['user_id'] != $current_user_id) {
                $_SESSION['msg_status'] = 'error';
                $_SESSION['msg_text'] = 'คุณไม่มีสิทธิ์แก้ไขรายงานนี้';
                header("Location: daily_report.php"); exit();
            }

            // UPDATE (เพิ่ม system_name)
            $stmt = $conn->prepare("UPDATE daily_reports SET report_date=?, time_range=?, system_name=?, task_detail=?, work_result=?, remark=? WHERE id=?");
            $stmt->bind_param("ssssssi", $report_date, $time_range, $system_name, $task_detail, $work_result, $remark, $id);
            $msg = "แก้ไขรายงานเรียบร้อยแล้ว";
        }

        if ($stmt->execute()) {
            $_SESSION['msg_status'] = 'success';
            $_SESSION['msg_text'] = $msg;
        } else {
            $_SESSION['msg_status'] = 'error';
            $_SESSION['msg_text'] = 'Error: ' . $conn->error;
        }

    } elseif ($action == 'delete') {
        $id = $_POST['del_id'];
        $sql_check = "SELECT user_id FROM daily_reports WHERE id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $id);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        $row_check = $res_check->fetch_assoc();

        if ($is_admin || $row_check['user_id'] == $current_user_id) {
            $stmt = $conn->prepare("DELETE FROM daily_reports WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['msg_status'] = 'success';
                $_SESSION['msg_text'] = 'ลบรายการเรียบร้อยแล้ว';
            }
        } else {
            $_SESSION['msg_status'] = 'error';
            $_SESSION['msg_text'] = 'คุณไม่มีสิทธิ์ลบรายงานนี้';
        }
    }
    header("Location: daily_report.php"); exit();
}

// --- 4. ส่วนเตรียมตัวกรอง (Filter) ---
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$display_header_name = $_SESSION['fullname']; 

if ($is_admin) {
    $filter_user = (isset($_GET['filter_user']) && $_GET['filter_user'] != '') ? $_GET['filter_user'] : $current_user_id;
    $sql_users = "SELECT id, fullname FROM users ORDER BY fullname ASC";
    $res_users = $conn->query($sql_users);
} else {
    $filter_user = $current_user_id;
}

// --- 5. Query ข้อมูล ---
$sql = "SELECT r.*, u.fullname 
        FROM daily_reports r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE DATE_FORMAT(r.report_date, '%Y-%m') = ? ";

if ($filter_user == 'all' && $is_admin) {
    $display_header_name = "พนักงานทั้งหมด (All Users)";
} else {
    $sql .= " AND r.user_id = " . intval($filter_user);
}

// $sql .= " ORDER BY r.report_date DESC, r.id DESC";
$sql .= " ORDER BY r.report_date,r.system_name ASC, r.id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $filter_month);
$stmt->execute();
$result = $stmt->get_result();

$report_date = date('Y-m-d');
// --- [ดึงข้อมูลเสริม] ส่วนนี้จะ query แยกเพื่อไม่ให้กระทบ $result หลัก ---
$sql_extra = "SELECT * FROM daily_reports WHERE report_date = ? AND user_id = ? LIMIT 1";
$stmt_extra = $conn->prepare($sql_extra);
$stmt_extra->bind_param("si", $report_date, $current_user_id); // ใช้ $report_date และ user ปัจจุบัน
$stmt_extra->execute();
$result_extra = $stmt_extra->get_result();

// ดึงแค่แถวเดียว ไม่ต้อง loop
$row_extra = $result_extra->fetch_assoc();
if ($row_extra) {
    $time_report = "";
} else {
    $time_report = "08:00-17:00";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>รายงานประจำวัน (IT Support)</title>
    <link href="/myproject_ww/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="/myproject_ww/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="/myproject_ww/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .table-custom th { background-color: #f8f9fc; color: #4e73df; vertical-align: middle; text-align: center; white-space: nowrap; }
        .table-custom td { vertical-align: middle; }
        .bg-result-success { background-color: #d1e7dd; color: #0f5132; border-radius: 5px; padding: 2px 8px; font-weight: bold; }
        .bg-result-pending { background-color: #fff3cd; color: #664d03; border-radius: 5px; padding: 2px 8px; font-weight: bold; }
        .system-badge { font-size: 0.85rem; font-weight: 500; }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include($root_path . '/includes/sidebar.php'); ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include($root_path . '/includes/topbar.php'); ?>
                
                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">รายงานการปฏิบัติงาน (Daily Report)</h1>
                        <form class="form-inline bg-white p-2 rounded shadow-sm border" method="GET">
                            <label class="mr-2 font-weight-bold text-primary">เดือน:</label>
                            <input type="month" name="month" class="form-control form-control-sm mr-3" value="<?php echo $filter_month; ?>" onchange="this.form.submit()">
                            
                            <?php if ($is_admin) { ?>
                                <label class="mr-2 font-weight-bold text-primary">พนักงาน:</label>
                                <select name="filter_user" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($filter_user == 'all') ? 'selected' : ''; ?>>-- ดูทั้งหมด --</option>
                                    <?php 
                                    if ($res_users) $res_users->data_seek(0);
                                    while($u = $res_users->fetch_assoc()) { 
                                        $sel = ($filter_user == $u['id']) ? 'selected' : '';
                                        if ($filter_user == $u['id']) $display_header_name = $u['fullname'];
                                    ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo $sel; ?>><?php echo $u['fullname']; ?></option>
                                    <?php } ?>
                                </select>
                            <?php } ?>
                            <button type="button" class="btn btn-sm btn-success shadow-sm ml-2" onclick="openModal('add')"><i class="fas fa-plus fa-sm text-white-50"></i> เพิ่มรายงาน</button>
                        </form>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-gradient-secondary">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-user-circle"></i> รายงานของ : <span style="text-decoration: underline;"><?php echo $display_header_name; ?></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-custom table-hover" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th width="10%">ว/ด/ป</th>
                                            <th width="8%">เวลา</th>
                                            <!-- <th width="5%">#</th> -->
                                            <th width="5%">##</th>
                                            
                                            <?php if($filter_user == 'all') { ?>
                                                <th width="12%">ผู้ปฏิบัติงาน</th>
                                            <?php } ?>

                                            <th width="12%">ระบบ</th> <th>งานที่ทำ</th>
                                            <th width="10%">ผลลัพธ์</th>
                                            <th width="12%">หมายเหตุ</th>
                                            <th width="8%">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $i = 1;

                                        $prev_date = null;
                                        $daily_no = 1;
                                        while ($row = $result->fetch_assoc()) {
                                            $date_th = date('d/m/', strtotime($row['report_date'])) . (date('Y', strtotime($row['report_date'])) + 543);

                                            // --- [Logic รันเลขใหม่ของวัน] ---
                                            if ($row['report_date'] != $prev_date) {
                                                // ถ้าวันที่เปลี่ยน ให้เริ่มนับ 1 ใหม่
                                                $daily_no = 1;
                                                $prev_date = $row['report_date']; // จำวันที่ปัจจุบันไว้เทียบรอบหน้า
                                            } else {
                                                // ถ้าวันเดิม ให้นับต่อ
                                                $daily_no++;
                                            }

                                            $is_owner = ($row['user_id'] == $current_user_id);
                                            $can_manage = ($is_admin || $is_owner);

                                            // หาสีของ Badge ระบบ
                                            $sys_color = isset($system_options[$row['system_name']]) ? $system_options[$row['system_name']] : 'secondary';
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $date_th; ?></td>
                                            <td class="text-center"><?php echo $row['time_range']; ?></td>
                                            <!-- <td class="text-center"><?php // echo $i++; ?></td> -->

                                            <?php if($filter_user == 'all') { ?>
                                                <td class="small font-weight-bold text-primary"><?php echo $row['fullname']; ?></td>
                                            <?php } ?>
                                            <td class="text-center"><?php echo $daily_no; ?></td>
                                            <td class="text-center">
                                                <span class="badge badge-<?php echo $sys_color; ?> system-badge w-100 py-2">
                                                    <?php echo ($row['system_name']) ? $row['system_name'] : '-'; ?>
                                                </span>
                                            </td>

                                            <td><?php echo nl2br(htmlspecialchars($row['task_detail'])); ?></td>
                                            <td class="text-center">
                                                <?php if($row['work_result'] == 'เสร็จแล้ว'): ?>
                                                    <span class="bg-result-success small">เสร็จแล้ว</span>
                                                <?php else: ?>
                                                    <span class="bg-result-pending small"><?php echo $row['work_result']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small"><?php echo $row['remark']; ?></td>
                                            
                                            <td class="text-center">
                                                <?php if ($can_manage) { ?>
                                                    <button class="btn btn-warning btn-sm btn-circle" onclick='openModal("edit", <?php echo json_encode($row); ?>)'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm btn-circle" onclick="confirmDelete(<?php echo $row['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php } else { ?>
                                                    <i class="fas fa-lock text-gray-300"></i>
                                                <?php } ?>
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

    <div class="modal fade" id="reportModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form action="daily_report.php" method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalTitle">บันทึกรายงาน</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="modal_id">
                        
                        <div class="alert alert-info py-2 small">
                            <i class="fas fa-user"></i> ผู้บันทึก: <b><?php echo $_SESSION['fullname']; ?></b>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>วันที่ปฏิบัติงาน <span class="text-danger">*</span></label>
                                <input type="text" name="report_date" id="modal_date" class="form-control date-picker" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>ช่วงเวลา</label>
                                <input type="text" name="time_range" id="modal_time" class="form-control" value="<?php echo $time_report;?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label>ระบบที่ดูแล <span class="text-danger">*</span></label>
                                <select name="system_name" id="modal_system" class="form-control" required>
                                    <option value="">-- เลือกระบบ --</option>
                                    <?php foreach ($system_options as $name => $color) { ?>
                                        <option value="<?php echo $name; ?>"><?php echo $name; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>รายละเอียดงานที่ทำ (Task Detail) <span class="text-danger">*</span></label>
                            <textarea name="task_detail" id="modal_task" class="form-control" rows="4" required placeholder="อธิบายสิ่งที่ทำ..."></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>ผลของงาน</label>
                                <select name="work_result" id="modal_result" class="form-control">
                                    <option value="เสร็จแล้ว">เสร็จแล้ว</option>
                                    <option value="กำลังดำเนินการ">กำลังดำเนินการ</option>
                                    <option value="ติดตามผล">ติดตามผล</option>
                                    <option value="ยกเลิก">ยกเลิก</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>หมายเหตุ</label>
                                <input type="text" name="remark" id="modal_remark" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form id="formDelete" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="del_id" id="del_id">
    </form>

    <script src="/myproject_ww/vendor/jquery/jquery.min.js"></script>
    <script src="/myproject_ww/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/myproject_ww/js/sb-admin-2.min.js"></script>
    <script src="/myproject_ww/vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="/myproject_ww/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable({ "order": [[ 0, "desc" ]] });
            $(".date-picker").flatpickr({ locale: "th", dateFormat: "Y-m-d", altInput: true, altFormat: "j F Y", defaultDate: "today" });
        });
        function openModal(mode, data = null) {
            if (mode === 'add') {
                $('#modalTitle').text('เพิ่มรายงานใหม่');
                $('#modal_id').val(''); $('#modal_task').val(''); $('#modal_remark').val(''); 
                $('#modal_result').val('เสร็จแล้ว'); $('#modal_system').val(''); // Reset System
            } else {
                $('#modalTitle').text('แก้ไขรายงาน');
                $('#modal_id').val(data.id);
                document.querySelector("#modal_date")._flatpickr.setDate(data.report_date);
                $('#modal_time').val(data.time_range); $('#modal_task').val(data.task_detail);
                $('#modal_result').val(data.work_result); $('#modal_remark').val(data.remark);
                $('#modal_system').val(data.system_name); // Set System
            }
            $('#reportModal').modal('show');
        }
        function confirmDelete(id) {
            Swal.fire({ title: 'ยืนยันการลบ?', text: "ลบรายการนี้ใช่หรือไม่?", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'ลบเลย' })
            .then((result) => { if (result.isConfirmed) { $('#del_id').val(id); $('#formDelete').submit(); } })
        }
    </script>
    <?php if (isset($_SESSION['msg_status'])) { ?>
        <script>Swal.fire({ icon: '<?php echo $_SESSION['msg_status']; ?>', title: '<?php echo $_SESSION['msg_text']; ?>', showConfirmButton: false, timer: 1500 });</script>
        <?php unset($_SESSION['msg_status']); unset($_SESSION['msg_text']); ?>
    <?php } ?>
</body>
</html>