<?php
// includes/sidebar.php

// 1. ตรวจสอบ Connection
if (!isset($conn)) {
    if (file_exists('../db.php')) {
        require_once '../db.php';
    } elseif (file_exists('db.php')) {
        require_once 'db.php';
    }
}

// 2. ตรวจสอบ Session Group ID
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
    // 3. ดึงข้อมูลเมนู
    if (isset($conn)) {
        $sql_menu = "SELECT m.* FROM menus m
                     INNER JOIN permissions p ON m.id = p.menu_id
                     WHERE p.group_id = '$current_group_id'
                     ORDER BY m.sort_order ASC";
        
        $result_menu = $conn->query($sql_menu);

        if ($result_menu) {
            $menu_items = [];
            $sub_menus = [];

            while ($row = $result_menu->fetch_assoc()) {
                if ($row['parent_id'] == 0) {
                    $menu_items[] = $row;
                } else {
                    $sub_menus[$row['parent_id']][] = $row;
                }
            }

            foreach ($menu_items as $menu) {
                $menu_id = $menu['id'];
                $has_sub = isset($sub_menus[$menu_id]);
                $is_active = false;
                $current_script = $_SERVER['PHP_SELF'];

                if ($menu['link'] != '#' && strpos($current_script, $menu['link']) !== false) {
                    $is_active = true;
                }

                if ($has_sub) {
                    foreach ($sub_menus[$menu_id] as $sub) {
                        if ($sub['link'] != '#' && strpos($current_script, $sub['link']) !== false) {
                            $is_active = true;
                            break;
                        }
                    }
                }
                
                $active_class = $is_active ? 'active' : '';

                if ($has_sub) {
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
                    ?>
                    <li class="nav-item <?php echo $active_class; ?>">
                        <a class="nav-link" href="<?php echo $menu['link']; ?>">
                            <i class="<?php echo $menu['icon']; ?>"></i>
                            <span><?php echo $menu['menu_name']; ?></span>
                        </a>
                    </li>
                    <?php
                }
            }
        }
    }
    ?>

    <hr class="sidebar-divider d-none d-md-block">
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>

<?php if (empty($disable_global_loader)) : ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&display=swap" rel="stylesheet">
    <style>
        body, h1, h2, h3, h4, h5, h6, p, span, div, a, li, button, input, select, textarea, label, .table, .btn, .sidebar {
            font-family: 'Sarabun', sans-serif !important;
        }
        body { font-size: 0.95rem; font-weight: 400; }
        h1, h2, h3, h4, h5, h6 { font-weight: 600 !important; }
    </style>

    <style>
        #global_loader {
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            z-index: 99999;
            /* สีพื้นหลังขาว (ไม่เบลอ เพื่อความลื่น) */
            background: rgba(255, 255, 255, 0.95);
            
            opacity: 1;
            visibility: visible;
            transition: opacity 0.3s, visibility 0.3s;
            
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        #global_loader.fade-out {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        #global_loader img {
            width: 80px; /* ปรับขนาดรูป GIF ตรงนี้ */
            height: 80px;
            margin-bottom: 15px;
        }
        
        .loading-text {
            color: #4e73df;
            font-weight: 600;
            font-size: 1rem;
        }
    </style>

    <div id="global_loader">
        <img src="../myproject_ww/img/loading.gif" alt="Loading...">
        <div class="loading-text">กำลังประมวลผล...</div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const loader = document.getElementById('global_loader');
            
            function hideLoader() { if(loader) loader.classList.add('fade-out'); }
            function showLoader() { if(loader) loader.classList.remove('fade-out'); }

            // 1. โหลดเสร็จสั่งซ่อน (สำหรับหน้าปกติ)
            window.addEventListener('load', () => setTimeout(hideLoader, 300));
            
            // 2. กันเหนียว: ปิดแน่นอนถ้าผ่านไป 5 วินาที
            setTimeout(hideLoader, 5000); 

            // 3. คลิกเปลี่ยนหน้า -> สั่งโชว์
            const links = document.querySelectorAll('a.nav-link, .collapse-item, a.btn, a.btn-circle');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    const target = this.getAttribute('target');
                    const isToggle = this.hasAttribute('data-toggle') || this.hasAttribute('data-target');
                    
                    // เพิ่มเงื่อนไข: ถ้าลิงก์นั้นเปิด Tab ใหม่ (target="_blank") ไม่ต้องโชว์ Loading
                    if (target === '_blank') return; 

                    if (href && href !== '#' && !href.startsWith('javascript') && !isToggle && !href.includes('#')) {
                        showLoader();
                    }
                });
            });

            // 4. Submit Form -> สั่งโชว์ (แก้ตรงนี้!!)
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    if (form.checkValidity()) {
                        // เช็คว่าฟอร์มนี้เปิด Tab ใหม่หรือเปล่า?
                        if (this.target === '_blank') {
                            // กรณีเปิด Tab ใหม่: โชว์แค่ 1.5 วินาที แล้วปิดเอง (กันค้าง)
                            showLoader();
                            setTimeout(hideLoader, 1500); 
                        } else {
                            // กรณีโหลดหน้าเดิม: โชว์ค้างไว้จนกว่าหน้าใหม่จะมา
                            showLoader();
                        }
                    }
                });
            });
            
            // 5. Back Button Fix
            window.addEventListener('pageshow', (event) => {
                if (event.persisted && loader) loader.classList.add('fade-out');
            });
        });
    </script>

<?php endif; ?>