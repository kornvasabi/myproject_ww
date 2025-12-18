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