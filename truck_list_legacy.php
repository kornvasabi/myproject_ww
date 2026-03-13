<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';
require_once $root_path . '/includes/logout_modal.php';

// เปิดใช้ถ้ามีการกำหนดสิทธิ์การเข้าถึงไฟล์นี้
checkAccess($conn, basename($_SERVER['PHP_SELF'])); 

// =========================================================
// STEP 0: Logic ค้นหาอัตโนมัติ (Auto-Search) 
// เมื่อกลับมาจากหน้า Action ระบบจะอ่านค่าจาก Session มาค้นหาให้ทันที
// =========================================================
// echo $_POST['btn_search']; exit;
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SESSION['search_branch_id'])) {
    $_POST['btn_search'] = true;
    $_POST['branch_id'] = $_SESSION['search_branch_id'];
    $_POST['date_start'] = $_SESSION['search_date_start'] ?? date('Y-m-d 00:00');
    $_POST['date_end'] = $_SESSION['search_date_end'] ?? date('Y-m-d 23:59');
    $_POST['ticket2'] = $_SESSION['search_ticket2'] ?? '';
}

// ล้างค่า Session หากมีการกดปุ่ม Reset (ส่ง ?reset=1 มา)
if (isset($_GET['reset'])) {
    unset($_SESSION['search_branch_id'], $_SESSION['search_date_start'], $_SESSION['search_date_end'], $_SESSION['search_ticket2']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ดึงรายชื่อสาขามาแสดงใน Dropdown
$sql_branch = "SELECT * FROM branches WHERE api_url IS NOT NULL AND api_url != ''";
$result_branch = $conn->query($sql_branch);

$search_results = [];

// =========================================================
// STEP 1: การจัดการเมื่อกดปุ่ม "ค้นหา" (Search Logic)
// =========================================================
if (/*$_SERVER['REQUEST_METHOD'] == 'POST' &&  */ isset($_POST['btn_search'])) {
    $branch_id = $_POST['branch_id'];
    $ticket2 = $_POST['ticket2'];
    $date_start_full = $_POST['date_start']; 
    $date_end_full = $_POST['date_end'];

    // เก็บค่าลง Session เพื่อให้จำไว้ใช้ในรอบหน้า (Persistence)
    $_SESSION['search_branch_id'] = $branch_id; 
    $_SESSION['search_date_start'] = $date_start_full;
    $_SESSION['search_date_end'] = $date_end_full;
    $_SESSION['search_ticket2'] = $ticket2;

    // เตรียมวันที่ส่งไป API (ตัดเหลือ Y-m-d เพื่อรองรับ API ระบบเก่า)
    $date_start_send = substr($date_start_full, 0, 10); 
    $date_end_send = substr($date_end_full, 0, 10);

    // ดึง URL API จากฐานข้อมูลสาขา
    $stmt = $conn->prepare("SELECT api_url FROM branches WHERE id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        // เปลี่ยนชื่อไฟล์ปลายทางให้ถูก (จาก Update เป็น Read)
        $api_url = str_replace("api_update_truck.php", "api_read_truck.php", $row['api_url']);
        
        $post_data = [
            'api_key' => 'KOR_SECRET_KEY_1234',
            'mode' => 'search',
            'ticket2' => $ticket2,
            'date_start' => $date_start_send,
            'date_end' => $date_end_send
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        // curl_close($ch);
        
        if ($response === false) {
            $_SESSION['api_status'] = 'error';
            $_SESSION['api_result'] = 'เชื่อมต่อล้มเหลว: ' . $curl_error;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }

        // --- ล้างขยะ JSON (Space/Notice) ก่อน Decode ---
        $clean_response = trim($response); 
        $start_pos = strpos($clean_response, '{');
        if ($start_pos !== false) {
            $clean_response = substr($clean_response, $start_pos);
        }
        
        $result = json_decode($clean_response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $_SESSION['api_status'] = 'error';
            $_SESSION['api_result'] = 'JSON Decode Error: ' . json_last_error_msg();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }

        if ($result && $result['status'] == 'success') {
            $search_results = $result['data'];
        } else {
            $_SESSION['api_status'] = 'warning';
            $_SESSION['api_result'] = "ไม่พบข้อมูล: " . ($result['message'] ?? 'Unknown Error');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ค้นหารายการชั่ง (Legacy)</title>
    <link href="/myproject_ww/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="/myproject_ww/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="/myproject_ww/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
    
    <style>
        .flatpickr-input[readonly] { background-color: #fff !important; cursor: pointer; }
        /* เอฟเฟกต์ Highlight เมื่อบันทึกสำเร็จ */
        .table-highlight { background-color: #fff3cd !important; transition: background-color 3s ease; }
        
        #loadingOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(3px);
            z-index: 9999; display: none; align-items: center; justify-content: center; flex-direction: column;
        }
       
        .loading-text { margin-top: 15px; color: #2e59d9; font-weight: 800; font-size: 1.2rem; text-shadow: 2px 2px 4px rgba(255,255,255,1); }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include($root_path . '/includes/sidebar.php'); ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include($root_path . '/includes/topbar.php'); ?>
                
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">ค้นหารายการชั่ง (ระบบเก่า)</h1>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">ตัวกรองค้นหา</h6></div>
                        <div class="card-body">
                            <form method="POST" id="searchForm">
                                <div class="form-row">
                                    <div class="col-md-3 mb-3">
                                        <label>เลือกสาขา *</label>
                                        <select name="branch_id" id="branch_id" class="form-control" required>
                                            <option value="">-- เลือกสาขา --</option>
                                            <?php 
                                            if($result_branch) $result_branch->data_seek(0);
                                            while($b = $result_branch->fetch_assoc()) { 
                                                $sel = (isset($_SESSION['search_branch_id']) && $_SESSION['search_branch_id'] == $b['id']) ? 'selected' : '';
                                                echo "<option value='{$b['id']}' $sel>{$b['branch_name']}</option>";
                                            } 
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>เริ่มต้น (วัน-เวลา)</label>
                                        <input type="text" name="date_start" class="form-control datetime-search" 
                                               value="<?php echo $_SESSION['search_date_start'] ?? date('Y-m-d 00:00'); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>สิ้นสุด (วัน-เวลา)</label>
                                        <input type="text" name="date_end" class="form-control datetime-search" 
                                               value="<?php echo $_SESSION['search_date_end'] ?? date('Y-m-d 23:59'); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>Ticket No</label>
                                        <input type="text" name="ticket2" class="form-control" placeholder="T-1234"
                                               value="<?php echo $_SESSION['search_ticket2'] ?? ''; ?>">
                                    </div>
                                </div>
                                <button type="submit" name="btn_search" class="btn btn-primary"><i class="fas fa-search"></i> ค้นหาข้อมูล</button>
                                <a href="?reset=1" class="btn btn-secondary ml-2"><i class="fas fa-sync"></i> ล้างค่า</a>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($search_results)) { ?>
                    <div class="card shadow mb-4">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-success">ผลการค้นหา (<?php echo count($search_results); ?> รายการ)</h6></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th><th>Ticket 2</th><th>In Date Time</th><th>Out Date Time</th><th class="text-center">พิมพ์บัตร</th><th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($search_results as $row) { 
                                            $is_printed = $row['is_printed'] ?? 0;
                                            // เช็คเพื่อทำ Highlight สีเหลืองที่แถวที่เพิ่งแก้
                                            $highlight = (isset($_SESSION['highlight_id']) && $_SESSION['highlight_id'] == $row['id']) ? 'table-highlight' : '';
                                        ?>
                                        <tr class="<?php echo $highlight; ?>">
                                            <td><?php echo $row['id']; ?></td>
                                            <td><?php echo $row['ticket2']; ?></td>
                                            <td><?php echo $row['in_date_time']; ?></td>
                                            <td><?php echo $row['out_date_time']; ?></td>
                                            <td class="text-center">
                                                <?php echo ($is_printed == 1) ? '<span class="badge badge-success">พิมพ์แล้ว</span>' : '<span class="badge badge-secondary">ยังไม่พิมพ์</span>'; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-warning btn-sm btn-edit"
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-in="<?php echo $row['in_date_time']; ?>"
                                                    data-out="<?php echo $row['out_date_time']; ?>"
                                                    data-ticket="<?php echo $row['ticket2']; ?>"
                                                    data-printed="<?php echo $is_printed; ?>">
                                                    <i class="fas fa-edit"></i> แก้ไข
                                                </button>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                </div>
            </div>
            <footer class="sticky-footer bg-white"><div class="container my-auto"><div class="copyright text-center my-auto"><span>Copyright &copy; Your System 2026</span></div></div></footer>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="truck_api_action.php" method="POST" id="editForm">
                    <div class="modal-header bg-gradient-warning text-white">
                        <h5 class="modal-title"><i class="far fa-clock"></i> แก้ไขข้อมูลจากสาขา</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="branch_id" id="modal_branch_id">
                        <div class="form-group row">
                            <div class="col-md-4">
                                <label class="small font-weight-bold">ID</label>
                                <input type="text" name="id" id="modal_id" class="form-control-plaintext font-weight-bold" readonly>
                            </div>
                            <div class="col-md-8">
                                <label class="small font-weight-bold">Ticket No</label>
                                <input type="text" id="modal_ticket" class="form-control-plaintext" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="text-primary font-weight-bold">เวลาเข้า (In)</label>
                            <input type="text" name="in_date_time" id="modal_in" class="form-control modal-datetime" required>
                        </div>
                        <div class="form-group">
                            <label class="text-danger font-weight-bold">เวลาออก (Out)</label>
                            <input type="text" name="out_date_time" id="modal_out" class="form-control modal-datetime">
                        </div>
                        <div class="form-group bg-gray-100 p-3 rounded">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="modal_printed" name="is_printed" value="1">
                                <label class="custom-control-label font-weight-bold" for="modal_printed">พิมพ์บัตรชั่งแล้ว (Printed)</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- div id="loadingOverlay">
        <div class="spinner-border text-primary loading-spinner" role="status"></div>
        <div class="loading-text">กำลังประมวลผล...</div>
    </div -->

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
            $('#loadingOverlay').hide();
            $('#dataTable').DataTable({ "order": [[ 0, "desc" ]] });

            $(".datetime-search").flatpickr({ locale: "th", enableTime: true, time_24hr: true, dateFormat: "Y-m-d H:i" });
            
            const fpConfig = { locale: "th", enableTime: true, enableSeconds: true, time_24hr: true, dateFormat: "Y-m-d H:i:S" };
            const fpIn = $("#modal_in").flatpickr(fpConfig);
            const fpOut = $("#modal_out").flatpickr(fpConfig);

            $('body').on('click', '.btn-edit', function() {
                const btn = $(this);
                $('#modal_id').val(btn.data('id'));
                $('#modal_ticket').val(btn.data('ticket'));
                $('#modal_branch_id').val($('#branch_id').val());
                fpIn.setDate(btn.data('in')); 
                if (btn.data('out')) fpOut.setDate(btn.data('out')); else fpOut.clear();
                $('#modal_printed').prop('checked', btn.data('printed') == 1);
                $('#editModal').modal('show');
            });

            $('form').on('submit', function() {
                if (this.checkValidity()) {
                    $('#loadingOverlay').css('display', 'flex');
                }
            });
        });
    </script>
    
    <?php 
    // แสดง SweetAlert แจ้งเตือนสถานะ
    if (isset($_SESSION['api_status'])) { 
        $status = $_SESSION['api_status'] == 'danger' ? 'error' : $_SESSION['api_status'];
        $msg = addslashes($_SESSION['api_result']);
        echo "<script>Swal.fire({icon:'$status', title:'แจ้งเตือน', text:'$msg', timer:2000});</script>";
        unset($_SESSION['api_status'], $_SESSION['api_result'], $_SESSION['highlight_id']); 
    } 
    ?>
</body>
</html>