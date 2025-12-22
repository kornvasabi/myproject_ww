<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

checkAccess($conn, basename($_SERVER['PHP_SELF'])); // เปิดใช้ถ้ากำหนดสิทธิ์แล้ว

$result_branch = $conn->query("SELECT * FROM branches WHERE api_url IS NOT NULL AND api_url != ''");

// ตัวแปรสำหรับเก็บข้อมูลที่จะ Pre-fill
$edit_data = [
    'id' => '',
    'in_date_time' => '',
    'out_date_time' => '',
    'branch_id' => ''
];

// --- [เพิ่ม] ส่วนดึงข้อมูลเก่าถ้ามี GET id มา ---
if (isset($_GET['id']) && isset($_GET['branch_id'])) {
    $get_id = $_GET['id'];
    $get_branch = $_GET['branch_id'];
    
    // หา URL API Read
    $stmt = $conn->prepare("SELECT api_url FROM branches WHERE id = ?");
    $stmt->bind_param("i", $get_branch);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($r = $res->fetch_assoc()) {
        $api_url = str_replace("api_update_truck.php", "api_read_truck.php", $r['api_url']);
        
        $post_data = [
            'api_key' => 'KOR_SECRET_KEY_1234',
            'mode' => 'get_one',
            'id' => $get_id
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        
        $json = json_decode($resp, true);
        if ($json && $json['status'] == 'success') {
            // แปลง Format วันที่ให้เข้ากับ HTML datetime-local (ต้องมี T ตรงกลาง)
            $row = $json['data'];
            $edit_data['id'] = $row['id'];
            $edit_data['branch_id'] = $get_branch;
            $edit_data['in_date_time'] = date('Y-m-d\TH:i:s', strtotime($row['in_date_time']));
            if($row['out_date_time']) {
                $edit_data['out_date_time'] = date('Y-m-d\TH:i:s', strtotime($row['out_date_time']));
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>แก้ไขเวลาชั่ง (Legacy)</title>
    <link href="/myproject_ww/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="/myproject_ww/css/sb-admin-2.min.css" rel="stylesheet">
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include($root_path . '/includes/sidebar.php'); ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include($root_path . '/includes/topbar.php'); ?>
                
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">แก้ไขเวลาเข้า-ออก (เชื่อมต่อระบบเก่า 5.6)</h1>

                    <?php if (isset($_SESSION['api_result'])) { ?>
                        <div class="alert alert-<?php echo $_SESSION['api_status']; ?> alert-dismissible fade show">
                            <?php echo $_SESSION['api_result']; ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php unset($_SESSION['api_result']); unset($_SESSION['api_status']); ?>
                    <?php } ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">แบบฟอร์มแก้ไขข้อมูล</h6></div>
                        <div class="card-body">
                            <form action="myproject_ww/truck_api_action.php" method="POST">
                                
                                <div class="form-group row">
                                    <div class="col-sm-12 mb-3">
                                        <label class="text-primary font-weight-bold">เลือกสาขา</label>
                                        <select name="branch_id" class="form-control" required>
                                            <option value="">-- กรุณาเลือกสาขา --</option>
                                            <?php 
                                            // ใช้ค่าจากที่ Search มา หรือ Session ล่าสุด
                                            $curr_branch = $edit_data['branch_id'] ? $edit_data['branch_id'] : ($_SESSION['last_branch_id'] ?? '');
                                            if ($result_branch) {
                                                $result_branch->data_seek(0); // รีเซ็ต pointer
                                                while($branch = $result_branch->fetch_assoc()) { 
                                                    $sel = ($curr_branch == $branch['id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $branch['id']; ?>" <?php echo $sel; ?>>
                                                    <?php echo $branch['branch_name']; ?> 
                                                </option>
                                            <?php } } ?>
                                        </select>
                                    </div>
                                </div>
                                <hr>

                                <div class="form-group row">
                                    <div class="col-sm-4 mb-3">
                                        <label>Transaction ID</label>
                                        <input type="number" name="id" class="form-control" required 
                                               value="<?php echo $edit_data['id']; ?>" placeholder="ระบุ ID">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-sm-6 mb-3">
                                        <label>เวลาเข้า (In Date Time)</label>
                                        <input type="datetime-local" name="in_date_time" class="form-control" required step="1"
                                               value="<?php echo $edit_data['in_date_time']; ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label>เวลาออก (Out Date Time)</label>
                                        <input type="datetime-local" name="out_date_time" class="form-control" step="1"
                                               value="<?php echo $edit_data['out_date_time']; ?>">
                                        <small class="text-muted">เว้นว่างได้หากรถยังไม่ออก</small>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-6">
                                        <a href="truck_list_legacy.php" class="btn btn-secondary btn-icon-split">
                                            <span class="icon text-white-50"><i class="fas fa-arrow-left"></i></span>
                                            <span class="text">กลับหน้าค้นหา</span>
                                        </a>
                                    </div>
                                    <div class="col-6 text-right">
                                        <button type="submit" class="btn btn-warning btn-icon-split">
                                            <span class="icon text-white-50"><i class="fas fa-save"></i></span>
                                            <span class="text">บันทึกการแก้ไข</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="sticky-footer bg-white"><div class="container my-auto"><div class="copyright text-center my-auto"><span>Copyright &copy; Your System 2025</span></div></div></footer>
        </div>
    </div>
    <script src="/myproject_ww/vendor/jquery/jquery.min.js"></script>
    <script src="/myproject_ww/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/myproject_ww/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="/myproject_ww/js/sb-admin-2.min.js"></script>
</body>
</html>
