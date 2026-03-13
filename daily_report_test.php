<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/includes/auth.php'; // ถ้ามี
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>รายงานประจำวัน (Daily Report)</title>
    <link href="/myproject_ww/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="/myproject_ww/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include($root_path . '/includes/sidebar.php'); ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include($root_path . '/includes/topbar.php'); ?>
                <div class="container-fluid">

                    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-file-pdf"></i> รายงานประจำวัน</h1>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">กำหนดเงื่อนไขรายงาน</h6>
                        </div>
                        <div class="card-body">
                            <form id="reportForm">
                                <div class="form-row align-items-end">
                                    <div class="col-md-3 mb-3">
                                        <label>ตั้งแต่วันที่</label>
                                        <input type="text" name="start_date" class="form-control date-picker" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label>ถึงวันที่</label>
                                        <input type="text" name="end_date" class="form-control date-picker" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <button type="submit" class="btn btn-danger" id="btn-print">
                                            <span id="loading-icon" style="display:none;"><i class="fas fa-spinner fa-spin"></i></span>
                                            <span id="btn-text"><i class="fas fa-print"></i> พิมพ์ PDF (Blob)</span>
                                        </button>
                                        <button type="button" class="btn btn-success ml-2" id="btn-excel">
                                            <span id="loading-excel" style="display:none;"><i class="fas fa-spinner fa-spin"></i></span>
                                            <span id="text-excel"><i class="fas fa-file-excel"></i> Excel</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // เปิดใช้งาน Date Picker
        $(".date-picker").flatpickr({ dateFormat: "Y-m-d" });

        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault(); // 1. ห้ามเปลี่ยนหน้า

            // 2. เตรียมปุ่ม Loading (ปุ่มเล็ก)
            let btn = document.getElementById('btn-print');
            let icon = document.getElementById('loading-icon');
            let text = document.getElementById('btn-text');
            
            btn.disabled = true;
            icon.style.display = 'inline-block';
            text.textContent = ' กำลังสร้าง PDF...';

            // ---------------------------------------------------------
            // [สำคัญ] ถ้ามี Global Loader หมุนอยู่ ให้จำไว้ว่าต้องปิดมันด้วย
            // (เพราะ Sidebar สั่งเปิดไปแล้วตอนกด submit)
            // ---------------------------------------------------------
            const globalLoader = document.getElementById('global_loader');

            // 3. ดึงค่าและยิง Fetch
            const formData = new FormData(this);
            const params = new URLSearchParams(formData).toString();

            fetch('daily_report_pdf.php?' + params)
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.blob(); 
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                window.open(url, '_blank');
                
                // --- คืนค่าปุ่ม ---
                btn.disabled = false;
                icon.style.display = 'none';
                text.innerHTML = '<i class="fas fa-print"></i> พิมพ์ PDF (Blob)';

                // --- [แก้ตรงนี้] สั่งปิด Global Loader ครับ ---
                if(globalLoader) globalLoader.classList.add('fade-out');
            })
            .catch(error => {
                alert('เกิดข้อผิดพลาด: ' + error.message);
                
                // --- คืนค่าปุ่ม ---
                btn.disabled = false;
                icon.style.display = 'none';
                text.innerHTML = '<i class="fas fa-print"></i> พิมพ์ PDF (Blob)';

                // --- [แก้ตรงนี้] สั่งปิด Global Loader แม้จะ Error ---
                if(globalLoader) globalLoader.classList.add('fade-out');
            });
        });

        document.getElementById('btn-excel').addEventListener('click', function() {
            let btn = document.getElementById('btn-excel');
            let icon = document.getElementById('loading-excel');
            let text = document.getElementById('text-excel');
            
            // 1. แสดง Loading
            btn.disabled = true;
            icon.style.display = 'inline-block';
            text.textContent = ' กำลังสร้าง Excel...';

            // 2. ดึงค่าจากฟอร์ม
            const form = document.getElementById('reportForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData).toString();

            // 3. ยิงไปที่ไฟล์ PHP สร้าง Excel
            fetch('daily_report_excel.php?' + params)
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.blob(); 
            })
            .then(blob => {
                // --- เทคนิคดาวน์โหลดไฟล์จาก Blob แบบไม่ต้องเปิดแท็บใหม่ ---
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                // ตั้งชื่อไฟล์ที่จะเซฟ
                // a.download = 'Daily_Report_' + formData.get('start_date') + '.xls'; 
                a.download = 'Daily_Report_' + formData.get('start_date') + '.xlsx';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                
                // 4. คืนค่าปุ่ม
                btn.disabled = false;
                icon.style.display = 'none';
                text.innerHTML = '<i class="fas fa-file-excel"></i> Excel';
            })
            .catch(error => {
                alert('เกิดข้อผิดพลาด: ' + error.message);
                btn.disabled = false;
                icon.style.display = 'none';
                text.innerHTML = '<i class="fas fa-file-excel"></i> Excel';
            });
        });
    </script>
</body>
</html>