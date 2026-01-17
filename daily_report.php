<?php
session_start();
// ตั้งค่า Path
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

checkAccess($conn, basename($_SERVER['PHP_SELF'])); // เปิดใช้ถ้ากำหนดสิทธิ์แล้ว

// --- ส่วนจัดการบันทึกข้อมูล (Save/Update/Delete) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'save') {
        $id = $_POST['id'];
        $report_date = $_POST['report_date'];
        $time_range = $_POST['time_range'];
        $task_detail = $_POST['task_detail'];
        $work_result = $_POST['work_result'];
        $remark = $_POST['remark'];
        $user_id = $_SESSION['user_id']; // ดึง ID คน Login

        if (empty($id)) {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO daily_reports (user_id, report_date, time_range, task_detail, work_result, remark) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $user_id, $report_date, $time_range, $task_detail, $work_result, $remark);
            $msg = "เพิ่มรายงานเรียบร้อยแล้ว";
        } else {
            // UPDATE
            $stmt = $conn->prepare("UPDATE daily_reports SET report_date=?, time_range=?, task_detail=?, work_result=?, remark=? WHERE id=?");
            $stmt->bind_param("sssssi", $report_date, $time_range, $task_detail, $work_result, $remark, $id);
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
        $stmt = $conn->prepare("DELETE FROM daily_reports WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['msg_status'] = 'success';
            $_SESSION['msg_text'] = 'ลบรายการเรียบร้อยแล้ว';
        }
    }
    
    header("Location: daily_report.php");
    exit();
}

// --- ส่วนดึงข้อมูลมาแสดง ---
// กรองเฉพาะเดือนปัจจุบัน หรือตามที่เลือก
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$sql = "SELECT r.*, u.fullname 
        FROM daily_reports r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE DATE_FORMAT(r.report_date, '%Y-%m') = '$filter_month'
        ORDER BY r.report_date DESC, r.id DESC";
$result = $conn->query($sql);
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
        /* ปรับแต่งตารางให้เหมือน Excel ในรูป */
        .table-custom th { background-color: #f8f9fc; color: #4e73df; vertical-align: middle; text-align: center; }
        .table-custom td { vertical-align: middle; }
        .bg-result-success { background-color: #d1e7dd; color: #0f5132; border-radius: 5px; padding: 2px 8px; font-weight: bold; }
        .bg-result-pending { background-color: #fff3cd; color: #664d03; border-radius: 5px; padding: 2px 8px; font-weight: bold; }
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
                        <form class="form-inline">
                            <label class="mr-2">เลือกเดือน:</label>
                            <input type="month" name="month" class="form-control mr-2" value="<?php echo $filter_month; ?>" onchange="this.form.submit()">
                            <button type="button" class="btn btn-success shadow-sm" onclick="openModal('add')">
                                <i class="fas fa-plus fa-sm text-white-50"></i> เพิ่มรายงาน
                            </button>
                        </form>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-warning">
                            <h6 class="m-0 font-weight-bold text-white">
                                <i class="fas fa-user-tag"></i> ฝ่ายสารสนเทศ : <?php echo $_SESSION['fullname']; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-custom table-hover" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th width="10%">ว/ด/ป</th>
                                            <th width="10%">เวลา</th>
                                            <th width="5%">ลำดับ</th>
                                            <th width="45%">งานที่ทำ</th>
                                            <th width="10%">ผลของงาน</th>
                                            <th width="10%">หมายเหตุ</th>
                                            <th width="10%">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $i = 1;
                                        while ($row = $result->fetch_assoc()) { 
                                            // จัด Format วันที่ไทย
                                            $date_th = date('d/m/', strtotime($row['report_date'])) . (date('Y', strtotime($row['report_date'])) + 543);
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $date_th; ?></td>
                                            <td class="text-center"><?php echo $row['time_range']; ?></td>
                                            <td class="text-center"><?php echo $i++; ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($row['task_detail'])); ?></td>
                                            <td class="text-center">
                                                <?php if($row['work_result'] == 'เสร็จแล้ว'): ?>
                                                    <span class="bg-result-success">เสร็จแล้ว</span>
                                                <?php else: ?>
                                                    <span class="bg-result-pending"><?php echo $row['work_result']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $row['remark']; ?></td>
                                            <td class="text-center">
                                                <button class="btn btn-warning btn-sm btn-circle" onclick='openModal("edit", <?php echo json_encode($row); ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm btn-circle btn-delete" data-id="<?php echo $row['id']; ?>">
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

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>วันที่ปฏิบัติงาน</label>
                                <input type="text" name="report_date" id="modal_date" class="form-control date-picker" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>ช่วงเวลา</label>
                                <input type="text" name="time_range" id="modal_time" class="form-control" value="08:00-17:00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>งานที่ทำ (Task Detail)</label>
                            <textarea name="task_detail" id="modal_task" class="form-control" rows="4" required placeholder="ระบุรายละเอียดงาน..."></textarea>
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

            $(".date-picker").flatpickr({
                locale: "th", dateFormat: "Y-m-d", altInput: true, altFormat: "j F Y", defaultDate: "today"
            });

            // ปุ่มลบ
            $('.btn-delete').click(function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: 'ยืนยันการลบ?', text: "ลบรายการนี้ใช่หรือไม่?", icon: 'warning',
                    showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'ลบเลย'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#del_id').val(id);
                        $('#formDelete').submit();
                    }
                })
            });
        });

        function openModal(mode, data = null) {
            if (mode === 'add') {
                $('#modalTitle').text('เพิ่มรายงานใหม่');
                $('#modal_id').val('');
                $('#modal_task').val('');
                $('#modal_remark').val('');
                $('#modal_result').val('เสร็จแล้ว');
                // วันที่กับเวลาใช้ค่า Default
            } else {
                $('#modalTitle').text('แก้ไขรายงาน');
                $('#modal_id').val(data.id);
                // set flatpickr date
                document.querySelector("#modal_date")._flatpickr.setDate(data.report_date);
                $('#modal_time').val(data.time_range);
                $('#modal_task').val(data.task_detail);
                $('#modal_result').val(data.work_result);
                $('#modal_remark').val(data.remark);
            }
            $('#reportModal').modal('show');
        }
    </script>

    <?php if (isset($_SESSION['msg_status'])) { ?>
        <script>
            Swal.fire({
                icon: '<?php echo $_SESSION['msg_status']; ?>',
                title: '<?php echo $_SESSION['msg_text']; ?>',
                showConfirmButton: false, timer: 1500
            });
        </script>
        <?php unset($_SESSION['msg_status']); unset($_SESSION['msg_text']); ?>
    <?php } ?>
</body>
</html>