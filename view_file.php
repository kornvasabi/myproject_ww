<?php
session_start();
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';

if (isset($_GET['type']) && isset($_GET['file'])) {
    
    $sql_conf = "SELECT config_value FROM system_config WHERE config_key = 'upload_path'";
    $res_conf = $conn->query($sql_conf);
    $row_conf = $res_conf->fetch_assoc();
    
    // Fallback path
    $base_path = ($row_conf && !empty($row_conf['config_value'])) ? $row_conf['config_value'] : $root_path . '/ww/uploads/';

    $type = $_GET['type']; 
    $filename = basename($_GET['file']); 
    $sub_folder = ($type == 'image') ? 'images/' : 'files/';
    $full_path = $base_path . $sub_folder . $filename;

    if (file_exists($full_path)) {
        
        // --- [แก้ไขจุดที่ Error] ---
        // แทนที่จะใช้ mime_content_type() เราจะเช็คนามสกุลเอาเองครับ
        $file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
        
        // กำหนด Mime Type ตามนามสกุล
        $mime_types = [
            'png' => 'image/png',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'sql' => 'text/plain'
        ];

        // ถ้าหานามสกุลไม่เจอ ให้ใช้ default
        $mime = isset($mime_types[$file_extension]) ? $mime_types[$file_extension] : 'application/octet-stream';
        
        header("Content-Type: $mime");
        // ---------------------------

        if ($type != 'image') {
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="'.basename($full_path).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($full_path));
        }
        
        readfile($full_path);
        exit;
    } else {
        http_response_code(404);
        echo "File not found.";
    }
}
?>