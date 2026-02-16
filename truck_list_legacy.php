<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

checkAccess($conn, basename($_SERVER['PHP_SELF'])); // เปิดใช้ถ้ากำหนดสิทธิ์แล้ว

// ดึงสาขา
$sql_branch = "SELECT * FROM branches WHERE api_url IS NOT NULL AND api_url != ''";
$result_branch = $conn->query($sql_branch);

$search_results = [];
$error_message = "";

// เมื่อกดปุ่มค้นหา
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_search'])) {
    $branch_id = $_POST['branch_id'];
    $ticket2 = $_POST['ticket2'];
    
    // รับค่าวันที่+เวลา จาก Flatpickr (Format: Y-m-d H:i)
    $date_start_full = $_POST['date_start']; 
    $date_end_full = $_POST['date_end'];

    $_SESSION['last_branch_id'] = $branch_id; 

    // --- ส่วนจัดการวันที่สำหรับส่งไป API ---
    // เนื่องจาก API เดิม (api_read_truck.php) เขียนไว้แบบเติม 00:00:00 เอง
    // เราต้องแยกส่วนวันที่ กับ เวลา หรือส่งไปแบบที่ API รองรับ
    // แต่วิธีที่ง่ายที่สุดโดยไม่ต้องแก้ API คือ: ส่งไปเฉพาะ "วันที่" ถ้า API ล็อคไว้
    // *แต่ถ้าคุณแก้ API ให้รับ Datetime เต็มๆ ได้ ให้ส่ง $date_start_full ไปเลย*
    
    // ในที่นี้ผมสมมติว่า API เก่ารับแค่ Y-m-d แล้วไปเติมเวลาเอง
    // ดังนั้นผมจะตัดเอาแค่ Y-m-d ส่งไปเพื่อให้ API ไม่ Error
    $date_start_send = substr($date_start_full, 0, 10); 
    $date_end_send = substr($date_end_full, 0, 10);
    // ------------------------------------

    $stmt = $conn->prepare("SELECT api_url FROM branches WHERE id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $api_url = str_replace("api_update_truck.php", "api_read_truck.php", $row['api_url']);
        
        $post_data = [
            'api_key' => 'KOR_SECRET_KEY_1234',
            'mode' => 'search',
            'ticket2' => $ticket2,
            'date_start' => $date_start_send, // ส่งแบบตัดเวลาออกเพื่อให้ API เก่าทำงานได้
            'date_end' => $date_end_send
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout 30 วินาที
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout 10 วินาที
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // ตรวจสอบว่าเชื่อมต่อ Server ได้หรือไม่
        if ($response === false || !empty($curl_error)) {
            $_SESSION['api_status'] = 'error';
            $_SESSION['api_result'] = 'ไม่สามารถเชื่อมต่อกับ Server ได้: ' . $curl_error;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
        
        $result = json_decode($response, true);
        if ($result && $result['status'] == 'success') {
            $search_results = $result['data'];
        } else {
            $_SESSION['api_status'] = 'warning';
            $_SESSION['api_result'] = "ค้นหาไม่เจอ หรือ เกิดข้อผิดพลาด: " . ($result['message'] ?? 'Unknown Error');
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
    <!-- base href="../" -->
    <title>ค้นหารายการชั่ง (Legacy)</title>
    
    <link href="/myproject_ww/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="/myproject_ww/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="/myproject_ww/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_blue.css">
    
    <style>
        .flatpickr-input[readonly] { background-color: #fff !important; cursor: pointer; }
        /* ปรับขนาดตัวเลือกเวลาให้ใหญ่ขึ้นหน่อย */
        .flatpickr-time input { font-size: 16px; }

        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            
            /* [แก้ไขตรงนี้] ปรับให้ใสครับ */
            background-color: rgba(255, 255, 255, 0.4); /* สีขาวจางๆ 40% (มองทะลุได้ 60%) */
            backdrop-filter: blur(3px); /* เพิ่มเอฟเฟกต์เบลอฉากหลังนิดๆ ให้ดูหรู */
            
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        
        .loading-spinner {
            width: 4rem;
            height: 4rem;
            /* เพิ่มเงาให้ตัวหมุนนิดนึง จะได้ไม่จมไปกับฉากหลัง */
            filter: drop-shadow(0 0 5px rgba(0,0,0,0.1)); 
        }
        
        .loading-text {
            margin-top: 15px;
            color: #2e59d9; /* สีน้ำเงินเข้มขึ้นหน่อยให้อ่านง่าย */
            font-weight: 800; /* ตัวหนาพิเศษ */
            font-size: 1.2rem;
            text-shadow: 2px 2px 4px rgba(255,255,255,1); /* ขอบขาวรอบตัวหนังสือ */
        }
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
                            <form method="POST">
                                <div class="form-row">
                                    <div class="col-md-3 mb-3">
                                        <label>เลือกสาขา *</label>
                                        <select name="branch_id" id="search_branch_id" class="form-control" required>
                                            <option value="">-- เลือกสาขา --</option>
                                            <?php 
                                            if($result_branch) $result_branch->data_seek(0);
                                            while($b = $result_branch->fetch_assoc()) { 
                                                $sel = (isset($_SESSION['last_branch_id']) && $_SESSION['last_branch_id'] == $b['id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $b['id']; ?>" <?php echo $sel; ?>><?php echo $b['branch_name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>เริ่มต้น (วัน-เวลา)</label>
                                        <input type="text" name="date_start" class="form-control datetime-search" 
                                               value="<?php echo isset($_POST['date_start']) ? $_POST['date_start'] : date('Y-m-d 00:00'); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>สิ้นสุด (วัน-เวลา)</label>
                                        <input type="text" name="date_end" class="form-control datetime-search" 
                                               value="<?php echo isset($_POST['date_end']) ? $_POST['date_end'] : date('Y-m-d 23:59'); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>Ticket No</label>
                                        <input type="text" name="ticket2" class="form-control" placeholder="เช่น T-1234" value="<?php echo isset($_POST['ticket2']) ? $_POST['ticket2'] : ''; ?>">
                                    </div>
                                </div>
                                <button type="submit" name="btn_search" class="btn btn-primary"><i class="fas fa-search"></i> ค้นหาข้อมูล</button>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($error_message)) { echo "<div class='alert alert-danger'>$error_message</div>"; } ?>
                    
                    <?php if (!empty($search_results)) { ?>
                    <div class="card shadow mb-4">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-success">ผลการค้นหา (<?php echo count($search_results); ?> รายการ)</h6></div>
                        <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>                <th>Ticket 2</th>          <th>In Date Time</th>      <th>Out Date Time</th>     <th class="text-center">สถานะพิมพ์</th> <th>จัดการ</th>             </tr>
                                </thead>
                                
                                <tbody>
                                    <?php foreach ($search_results as $row) { 
                                        // ดึงค่าสถานะพิมพ์ (ถ้าไม่มีให้เป็น 0)
                                        $is_printed = isset($row['is_printed']) ? $row['is_printed'] : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>

                                        <td><?php echo $row['ticket2']; ?></td>

                                        <td><?php echo $row['in_date_time']; ?></td>

                                        <td><?php echo $row['out_date_time']; ?></td>
                                        
                                        <td class="text-center">
                                            <?php if($is_printed == 1): ?>
                                                <span class="badge badge-success px-2 py-1">
                                                    <i class="fas fa-check"></i> พิมพ์แล้ว
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary px-2 py-1">
                                                    <i class="fas fa-times"></i> ยังไม่พิมพ์
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm btn-edit"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-in="<?php echo $row['in_date_time']; ?>"
                                                data-out="<?php echo $row['out_date_time']; ?>"
                                                data-ticket="<?php echo $row['ticket2']; ?>"
                                                data-printed="<?php echo $is_printed; ?>" 
                                            >
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
            <footer class="sticky-footer bg-white"><div class="container my-auto"><div class="copyright text-center my-auto"><span>Copyright &copy; Your System 2025</span></div></div></footer>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="truck_api_action.php" method="POST">
                    <div class="modal-header bg-gradient-warning text-white">
                        <h5 class="modal-title"><i class="far fa-clock"></i> แก้ไขเวลา</h5>
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
                        <hr>
                        <div class="form-group">
                            <label class="text-primary font-weight-bold"><i class="fas fa-sign-in-alt"></i> เวลาเข้า (In)</label>
                            <input type="text" name="in_date_time" id="modal_in" class="form-control modal-datetime" required>
                        </div>
                        <div class="form-group">
                            <label class="text-danger font-weight-bold"><i class="fas fa-sign-out-alt"></i> เวลาออก (Out)</label>
                            <input type="text" name="out_date_time" id="modal_out" class="form-control modal-datetime">
                        </div>
                        <div class="form-group bg-gray-200 p-3 rounded">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="modal_printed" name="is_printed" value="1">
                                <label class="custom-control-label font-weight-bold text-dark" for="modal_printed">
                                    <i class="fas fa-print"></i> พิมพ์บัตรชั่งแล้ว (Printed)
                                </label>
                            </div>
                            <small class="text-muted ml-4">หากติ๊กออก สถานะจะกลับเป็น "ยังไม่พิมพ์"</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include('includes/logout_modal.php'); ?>
    
    <div id="loadingOverlay">
        <div class="spinner-border text-primary loading-spinner" role="status">
            <span class="sr-only">Loading...</span>
        </div>
        <div class="loading-text">กำลังค้นหาข้อมูลจาก Server เก่า...</div>
    </div>

    <script src="/myproject_ww/vendor/jquery/jquery.min.js"></script>
    <script src="/myproject_ww/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/myproject_ww/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="/myproject_ww/js/sb-admin-2.min.js"></script>
    <script src="/myproject_ww/vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="/myproject_ww/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // ซ่อน Loading ทันทีเมื่อหน้าเว็บโหลดเสร็จ
            $('#loadingOverlay').hide();
            
            $('#dataTable').DataTable({ "order": [[ 0, "desc" ]] });

            $(".datetime-search").flatpickr({
                locale: "th",
                enableTime: true,
                time_24hr: true,
                dateFormat: "Y-m-d H:i",
                altInput: true,
                altFormat: "j F Y, H:i"
            });
            
            const fpConfig = {
                locale: "th",
                enableTime: true,
                enableSeconds: true,
                time_24hr: true,
                dateFormat: "Y-m-d H:i:S",
                altInput: true,
                altFormat: "j F Y, H:i:S"
            };
            const fpIn = $("#modal_in").flatpickr(fpConfig);
            const fpOut = $("#modal_out").flatpickr(fpConfig);

            $('body').on('click', '.btn-edit', function() {
                var id = $(this).data('id');
                var ticket = $(this).data('ticket');
                var inTime = $(this).data('in');
                var outTime = $(this).data('out');
                var printed = $(this).data('printed'); // รับค่า 0 หรือ 1
                var branchId = $('#search_branch_id').val();
                
                $('#modal_id').val(id);
                $('#modal_ticket').val(ticket);
                $('#modal_branch_id').val(branchId);
                
                fpIn.setDate(inTime); 
                if (outTime) fpOut.setDate(outTime); else fpOut.clear();

                // ตั้งค่า Checkbox
                if (printed == 1) {
                    $('#modal_printed').prop('checked', true);
                } else {
                    $('#modal_printed').prop('checked', false);
                }
                
                $('#editModal').modal('show');
            });

            // แสดง Loading เมื่อกดค้นหาหรือบันทึก
            $('form').on('submit', function(e) {
                if (this.checkValidity()) {
                    // ถ้าเป็นฟอร์มค้นหา
                    /*if($(this).find('[name="btn_search"]').length > 0) {
                        $('#loadingOverlay .loading-text').text('กำลังค้นหาข้อมูลจาก Server เก่า...');
                        $('#loadingOverlay').css('display', 'flex');
                    }*/
                    // ถ้าเป็นฟอร์มใน Modal (บันทึกการแก้ไข)
                    else if($(this).closest('#editModal').length > 0) {
                        $('#loadingOverlay .loading-text').text('กำลังบันทึกข้อมูล...');
                        $('#loadingOverlay').css('display', 'flex');
                    }
                }
            });
        });
    </script>
    
    <?php 
    if (isset($_SESSION['api_status']) && isset($_SESSION['api_result'])) { 
        $status = $_SESSION['api_status']; // success, warning, error
        $msg = $_SESSION['api_result'];
        
        // แปลง danger เป็น error เพื่อให้ icon ของ SweetAlert ถูกต้อง
        if($status == 'danger') $status = 'error'; 
    ?>
        <script>
            // ปิด Loading ก่อนแสดง SweetAlert
            $('#loadingOverlay').hide();
            
            Swal.fire({
                icon: '<?php echo $status; ?>',
                title: '<?php echo ($status == "success" ? "สำเร็จ!" : ($status == "error" ? "เกิดข้อผิดพลาด!" : "แจ้งเตือน")); ?>',
                text: '<?php echo addslashes($msg); ?>',
                showConfirmButton: true,
                timer: <?php echo ($status == "success" ? 2000 : "null"); ?> // ถ้าสำเร็จให้ปิดเอง ถ้า error ให้กดปิด
            });
        </script>
        <?php 
        // ล้างค่า Session ทิ้งทันทีที่แสดงผลเสร็จ
        unset($_SESSION['api_result']); 
        unset($_SESSION['api_status']); 
    } 
    ?>
</body>
</html>