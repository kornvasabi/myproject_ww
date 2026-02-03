<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

// 1. ดึงประเภท
$types_opt = [];
$sql_type = "SELECT * FROM issue_types WHERE is_active = 1";
$res_type = $conn->query($sql_type);
while ($t = $res_type->fetch_assoc()) {
    $types_opt[] = $t;
}

// 2. รับค่าค้นหา
$search_type = isset($_GET['search_type']) ? $_GET['search_type'] : '';

// 3. Query ข้อมูล
$sql = "SELECT i.*, t.name as type_name, u.fullname 
        FROM issues i
        LEFT JOIN issue_types t ON i.type_id = t.id
        LEFT JOIN users u ON i.user_id = u.id 
        WHERE 1=1 ";

if (!empty($search_type)) {
    $sql .= " AND i.type_id = " . intval($search_type);
}

$sql .= " ORDER BY i.created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>บันทึกการแก้ไขปัญหาระบบ</title>
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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Knowledge Base</h1>
                        
                        <div class="form-inline">
                            <form method="GET" class="mr-2">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-primary text-white border-0"><i class="fas fa-filter"></i></span>
                                    </div>
                                    <select name="search_type" class="form-control border-0 shadow-sm" onchange="this.form.submit()" style="min-width: 200px;">
                                        <option value="">-- ดูทั้งหมด --</option>
                                        <?php foreach ($types_opt as $t) { 
                                            $sel = ($search_type == $t['id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $t['id']; ?>" <?php echo $sel; ?>><?php echo $t['name']; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </form>

                            <button class="btn btn-success shadow-sm" onclick="openModal('add')">
                                <i class="fas fa-plus fa-sm text-white-50"></i> เพิ่มรายการ
                            </button>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">รายการปัญหาล่าสุด</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>ประเภท</th>
                                            <th>หัวข้อปัญหา</th>
                                            <th>ผู้บันทึก</th>
                                            <th>ไฟล์แนบ</th>
                                            <th>วันที่</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()) { ?>
                                        <tr>
                                            <td><?php echo $row['id']; ?></td>
                                            <td><span class="badge badge-info"><?php echo $row['type_name']; ?></span></td>
                                            <td>
                                                <div class="font-weight-bold"><?php echo htmlspecialchars($row['title']); ?></div>
                                                <small class="text-muted"><?php echo mb_substr($row['description'], 0, 50) . '...'; ?></small>
                                            </td>
                                            <td><?php echo $row['fullname']; ?></td>
                                            
                                            <td class="text-center">
                                                <?php if(!empty($row['image_path'])): ?>
                                                    <a href="view_file.php?type=image&file=<?php echo $row['image_path']; ?>" target="_blank" class="btn btn-sm btn-circle btn-primary" title="ดูรูปภาพ">
                                                        <i class="fas fa-image"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if(!empty($row['file_path'])): ?>
                                                    <a href="view_file.php?type=file&file=<?php echo $row['file_path']; ?>" target="_blank" class="btn btn-sm btn-circle btn-success" title="ดาวน์โหลดเอกสาร">
                                                        <i class="fas fa-file-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>

                                            <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-warning btn-sm" 
                                                    onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)">
                                                    <i class="fas fa-edit"></i>
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

    <div class="modal fade" id="issueModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form action="issue_save.php" method="POST" enctype="multipart/form-data" id="formIssue">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalTitle">บันทึกปัญหา</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="modal_id">
                        <input type="hidden" name="old_image" id="modal_old_image">
                        <input type="hidden" name="old_file" id="modal_old_file">

                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label>หัวข้อปัญหา <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="modal_title" class="form-control" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label>ประเภทระบบ <span class="text-danger">*</span></label>
                                <select name="type_id" id="modal_type" class="form-control" required>
                                    <option value="">-- เลือกประเภท --</option>
                                    <?php foreach ($types_opt as $t) { ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo $t['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>รายละเอียด / วิธีแก้ไข</label>
                            <textarea name="description" id="modal_desc" class="form-control" rows="5"></textarea>
                        </div>

                        <hr>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label><i class="fas fa-image"></i> อัปโหลดรูปภาพ</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="upload_image" id="fileImage" accept="image/*">
                                    <label class="custom-file-label" for="fileImage">เลือกรูปภาพ...</label>
                                </div>
                                <small id="link_image" class="form-text text-muted"></small>
                            </div>
                            <div class="form-group col-md-6">
                                <label><i class="fas fa-paperclip"></i> อัปโหลดไฟล์เอกสาร</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="upload_file" id="fileDoc" accept=".xlsx,.xls,.doc,.docx,.pdf,.txt,.sql">
                                    <label class="custom-file-label" for="fileDoc">เลือกไฟล์...</label>
                                </div>
                                <small id="link_file" class="form-text text-muted"></small>
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

    <script src="/myproject_ww/vendor/jquery/jquery.min.js"></script>
    <script src="/myproject_ww/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/myproject_ww/js/sb-admin-2.min.js"></script>
    <script src="/myproject_ww/vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="/myproject_ww/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable({ "order": [[ 0, "desc" ]] });
            $(".custom-file-input").on("change", function() {
                var fileName = $(this).val().split("\\").pop();
                $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
            });
        });

        function openModal(mode, data = null) {
            // 1. ระบุ ID ฟอร์มให้ชัดเจน (แก้ปัญหาไป Reset ผิดฟอร์ม)
            $('#formIssue')[0].reset();

            // 2. ล้างค่า Label ไฟล์ และ Link ตัวอย่าง
            $('.custom-file-label').html('เลือกไฟล์...');
            $('#link_image').html('');
            $('#link_file').html('');

            // 3. ล้างค่า Hidden Fields (สำคัญ! เพราะ reset() ไม่ลบช่องพวกนี้)
            $('#modal_id').val('');
            $('#modal_old_image').val('');
            $('#modal_old_file').val('');

            if (mode === 'add') {
                // --- โหมดเพิ่ม ---
                $('#modalTitle').text('เพิ่มรายการใหม่');
                // ไม่ต้องสั่งเคลียร์ title/desc อีก เพราะบรรทัด reset() ข้างบนทำไปแล้ว
            } else {
                // --- โหมดแก้ไข ---
                $('#modalTitle').text('แก้ไขรายการ: ' + data.id);
                
                // หยอดข้อมูลลงช่อง
                $('#modal_id').val(data.id);
                $('#modal_title').val(data.title);
                $('#modal_type').val(data.type_id);
                $('#modal_desc').val(data.description);
                
                // หยอดค่าไฟล์เก่า (Hidden)
                $('#modal_old_image').val(data.image_path);
                $('#modal_old_file').val(data.file_path);

                // แสดงสถานะไฟล์เดิม
                if(data.image_path) $('#link_image').html('<span class="text-success"><i class="fas fa-check"></i> มีรูปเดิมอยู่แล้ว</span>');
                if(data.file_path) $('#link_file').html('<span class="text-success"><i class="fas fa-check"></i> มีไฟล์เดิมอยู่แล้ว</span>');
            }

            // เปิด Modal
            $('#issueModal').modal('show');
        }
    </script>
    
    <?php if (isset($_SESSION['msg_status'])) { ?>
        <script>Swal.fire({ icon: '<?php echo $_SESSION['msg_status']; ?>', title: '<?php echo $_SESSION['msg_text']; ?>', showConfirmButton: false, timer: 1500 });</script>
        <?php unset($_SESSION['msg_status']); unset($_SESSION['msg_text']); ?>
    <?php } ?>
</body>
</html>