<?php
// includes/sidebar.php

// 1. ตรวจสอบ Connection (ป้องกัน Error หากไฟล์นี้ถูกเรียกโดยตรงหรือ path ผิด)
if (!isset($conn)) {
    if (file_exists('../db.php')) {
        require_once '../db.php';
    } elseif (file_exists('db.php')) {
        require_once 'db.php';
    }
}

// 2. ตรวจสอบ Session Group ID (ถ้าไม่มี ให้เป็น 0)
$current_group_id = isset($_SESSION['group_id']) ? $_SESSION['group_id'] : 0;
?>

<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
        <div class="sidebar-brand-icon rotate-n-15">
            <i class="fas fa-laugh-wink"></i>
        </div>
        <div class="sidebar-brand-text mx-3">SB Admin <sup>2</sup></div>
    </a>

    <hr class="sidebar-divider my-0">

    <?php
    // 3. เริ่มดึงข้อมูลเมนูจาก Database
    if (isset($conn)) {
        // ดึงเมนูที่ Group นี้มีสิทธิ์เห็น
        $sql_menu = "SELECT m.* FROM menus m
                     INNER JOIN permissions p ON m.id = p.menu_id
                     WHERE p.group_id = '$current_group_id'
                     ORDER BY m.sort_order ASC";
        
        $result_menu = $conn->query($sql_menu);

        if ($result_menu) {
            // 3.1 แยกเมนูเป็น 2 กลุ่ม: พ่อ (Parent) และ ลูก (Child)
            $menu_items = [];
            $sub_menus = [];

            while ($row = $result_menu->fetch_assoc()) {
                if ($row['parent_id'] == 0) {
                    $menu_items[] = $row; // เมนูหลัก
                } else {
                    $sub_menus[$row['parent_id']][] = $row; // เมนูย่อย (เก็บตาม ID พ่อ)
                }
            }

            // 3.2 วนลูปแสดงผลเมนู
            foreach ($menu_items as $menu) {
                $menu_id = $menu['id'];
                $has_sub = isset($sub_menus[$menu_id]); // เช็คว่ามีลูกไหม

                // --- Logic การเช็ค Active (รองรับ Subfolder ww/) ---
                $is_active = false;
                $current_script = $_SERVER['PHP_SELF']; // URL ปัจจุบัน

                // เช็คตัวมันเอง
                if ($menu['link'] != '#' && strpos($current_script, $menu['link']) !== false) {
                    $is_active = true;
                }

                // เช็คลูกของมัน (ถ้าลูก Active -> พ่อต้อง Active ด้วย)
                if ($has_sub) {
                    foreach ($sub_menus[$menu_id] as $sub) {
                        if ($sub['link'] != '#' && strpos($current_script, $sub['link']) !== false) {
                            $is_active = true;
                            break; // เจอลูก Active แล้ว หยุดเช็ค
                        }
                    }
                }
                
                $active_class = $is_active ? 'active' : '';

                // --- แสดงผล ---
                if ($has_sub) {
                    // === กรณีมีเมนูย่อย (Collapse Menu) ===
                    $collapse_id = "collapse_" . $menu_id;
                    $show_class = $is_active ? 'show' : ''; 
                    $collapsed_attr = $is_active ? '' : 'collapsed';
                    ?>
                    
                    <li class="nav-item <?php echo $active_class; ?>">
                        <a class="nav-link <?php echo $collapsed_attr; ?>" href="#" data-toggle="collapse" data-target="#<?php echo $collapse_id; ?>"
                            aria-expanded="true" aria-controls="<?php echo $collapse_id; ?>">
                            <i class="<?php echo $menu['icon']; ?>"></i>
                            <span><?php echo $menu['menu_name']; ?></span>
                        </a>
                        <div id="<?php echo $collapse_id; ?>" class="collapse <?php echo $show_class; ?>" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
                            <div class="bg-white py-2 collapse-inner rounded">
                                <h6 class="collapse-header">เมนูย่อย:</h6>
                                <?php 
                                foreach ($sub_menus[$menu_id] as $sub) { 
                                    // เช็ค Active ของลูกแต่ละตัว
                                    $sub_active = (strpos($current_script, $sub['link']) !== false) ? 'active' : '';
                                ?>
                                    <a class="collapse-item <?php echo $sub_active; ?>" href="<?php echo $sub['link']; ?>">
                                        <?php echo $sub['menu_name']; ?>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </li>

                    <?php
                } else {
                    // === กรณีเมนูธรรมดา (Single Link) ===
                    ?>
                    <li class="nav-item <?php echo $active_class; ?>">
                        <a class="nav-link" href="<?php echo $menu['link']; ?>">
                            <i class="<?php echo $menu['icon']; ?>"></i>
                            <span><?php echo $menu['menu_name']; ?></span>
                        </a>
                    </li>
                    <?php
                }
            } // จบ foreach
        } // จบ if result
    } // จบ if conn
    ?>

    <hr class="sidebar-divider d-none d-md-block">
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
<div id="global_loader">
    <div class="smooth-spinner"></div>
    <div class="loading-text">กำลังประมวลผล...</div>
</div>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&display=swap" rel="stylesheet">

<style>
    /* บังคับเปลี่ยน Font ทุกส่วนของเว็บ */
    body, 
    h1, h2, h3, h4, h5, h6, 
    p, span, div, a, li, 
    button, input, select, textarea, label,
    .table, .btn, .sidebar {
        font-family: 'Sarabun', sans-serif !important;
    }

    /* ปรับขนาดตัวอักษรพื้นฐานให้ใหญ่ขึ้นนิดนึง (Sarabun หัวเล็ก) */
    body {
        font-size: 0.95rem; 
        font-weight: 400;
    }
    
    /* ปรับหัวข้อให้หนาชัดเจน */
    h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 {
        font-weight: 600 !important;
    }

    /* ฉากหลัง Loading */
    #global_loader {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        z-index: 99999;
        
        background: rgba(255, 255, 255, 0.6);
        backdrop-filter: blur(10px); 
        -webkit-backdrop-filter: blur(10px);

        /* [แก้จุดที่ 1] ตั้งค่าเริ่มต้นให้ "โชว์" เสมอ */
        opacity: 1;
        visibility: visible;
        
        /* Transition */
        transition: opacity 0.4s ease-in-out, visibility 0.4s ease-in-out;
        
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    /* [แก้จุดที่ 2] สร้าง Class สำหรับ "ซ่อน" (Fade Out) */
    #global_loader.fade-out {
        opacity: 0;
        visibility: hidden;
    }

    /* Spinner Design (คงเดิม) */
    .smooth-spinner {
        width: 60px; height: 60px; border-radius: 50%; position: relative;
        border: 4px solid rgba(78, 115, 223, 0.1); border-left-color: #4e73df;
        animation: spin 0.8s linear infinite; box-shadow: 0 0 15px rgba(78, 115, 223, 0.2);
    }
    .loading-text {
        margin-top: 15px; color: #4e73df; font-weight: 600; letter-spacing: 1px;
        animation: pulse 1.5s infinite ease-in-out;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    @keyframes pulse { 0%, 100% { opacity: 0.6; } 50% { opacity: 1; } }
</style>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const loader = document.getElementById('global_loader');

        // ฟังก์ชันสั่งซ่อน (เติม Class fade-out)
        function hideLoader() {
            loader.classList.add('fade-out');
        }

        // ฟังก์ชันสั่งโชว์ (ลบ Class fade-out ออก -> กลับไปโชว์ตาม Default CSS)
        function showLoader() {
            loader.classList.remove('fade-out');
        }

        // A. เมื่อโหลดหน้าเสร็จ -> สั่งซ่อน Loader
        setTimeout(hideLoader, 300); // หน่วงนิดนึงให้เห็น Effect

        // B. ดักจับการคลิกเปลี่ยนหน้า -> สั่งโชว์ Loader กลับมา
        const links = document.querySelectorAll('a.nav-link, .collapse-item, a.btn, a.btn-circle');
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                const target = this.getAttribute('target');
                // เช็คว่าเป็นลิงก์เปิด Modal หรือ Dropdown หรือไม่ (ถ้าใช่ ไม่ต้องโชว์)
                const isToggle = this.hasAttribute('data-toggle') || this.hasAttribute('data-target');
                
                if (href && href !== '#' && !href.startsWith('javascript') && target !== '_blank' && !isToggle && !href.includes('#')) {
                    showLoader(); // ดึง Loader กลับมาบังจอ
                }
            });
        });

        // C. ดักจับการ Submit Form
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                if (form.checkValidity()) {
                    showLoader();
                }
            });
        });
    });
    
    // D. กรณีแก้ปัญหา Back Button ของ Browser (Safari/Chrome Mobile)
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            document.getElementById('global_loader').classList.add('fade-out');
        }
    });
</script>