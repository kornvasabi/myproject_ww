<?php
/**
 * Server Status Checker with Telegram Notification
 * ตรวจสอบสถานะเซิร์ฟเวอร์และส่งแจ้งเตือนผ่าน Telegram 
 */

require_once 'db.php';

// ========== การตั้งค่า Telegram Bot ==========
define('TELEGRAM_BOT_TOKEN', '8114135707:AAGrAKtF6vgekhlCgEDEtzcrv8uwFI2hCtE'); // ใส่ Token ของ Bot
// define('TELEGRAM_CHAT_ID', '7754054025');     // ใส่ Chat ID
define('TELEGRAM_CHAT_ID', '-4845052221');     // ใส่ Chat ID Group

// ========== การตั้งค่าการทำงาน ==========
define('TIMEOUT', 10); // Timeout สำหรับการเชื่อมต่อ (วินาที)
define('LOG_FILE', __DIR__ . '/../log_server_monitor/server_check.log'); // ไฟล์ Log
define('STATE_FILE', __DIR__ . '/../log_server_monitor/server_states.json'); // เก็บสถานะเซิร์ฟเวอร์

// ========== ฟังก์ชันส่งข้อความผ่าน Telegram ==========
function sendTelegramMessage($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode == 200;
}

// ========== ฟังก์ชันตรวจสอบสถานะเซิร์ฟเวอร์ ==========
function checkServerStatus($address, $timeout = 10) {
    if (empty($address)) {
        return 'Offline';
    }

    // เพิ่ม protocol ถ้าไม่มี
    if (!preg_match('~^https?://~i', $address)) {
        $address = 'http://' . $address;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $address,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_FAILONERROR => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Server-Monitor/1.0',
    ]);

    try {
        @curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch);
        curl_close($ch);

        // ถ้า HTTP code มีค่า ถือว่า Online
        if ($httpCode > 0) {
            return 'Online';
        } else {
            return 'Offline';
        }
    } catch (Exception $e) {
        if (is_resource($ch)) {
            curl_close($ch);
        }
        return 'Offline';
    }
}

// ========== ฟังก์ชันบันทึก Log ==========
function writeLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
    echo $logMessage;
}

// ========== ฟังก์ชันโหลดสถานะเซิร์ฟเวอร์ก่อนหน้า ==========
function loadServerStates() {
    if (file_exists(STATE_FILE)) {
        $content = file_get_contents(STATE_FILE);
        return json_decode($content, true) ?: [];
    }
    return [];
}

// ========== ฟังก์ชันบันทึกสถานะเซิร์ฟเวอร์ ==========
function saveServerStates($states) {
    file_put_contents(STATE_FILE, json_encode($states, JSON_PRETTY_PRINT));
}

// ========== เริ่มการทำงาน ==========
writeLog("========== เริ่มตรวจสอบเซิร์ฟเวอร์ ==========");

try {
    // ตรวจสอบการเชื่อมต่อ Database
    if (!isset($conn) || $conn === null) {
        throw new Exception('Database connection failed');
    }

    // โหลดสถานะเซิร์ฟเวอร์ก่อนหน้า
    $previousStates = loadServerStates();
    $currentStates = [];

    // ดึงข้อมูลเซิร์ฟเวอร์จากฐานข้อมูล
    $query = "SELECT id, name, address FROM servers where is_active = 1 ORDER BY id ASC";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception('Database query error: ' . $conn->error);
    }

    $offlineServers = [];
    $onlineServers = [];
    $statusChanged = [];

    while ($row = $result->fetch_assoc()) {
        $serverId = (int)$row['id'];
        $serverName = $row['name'];
        $serverAddress = trim($row['address']);

        writeLog("กำลังตรวจสอบ: {$serverName} ({$serverAddress})");

        // ตรวจสอบสถานะ
        $currentStatus = checkServerStatus($serverAddress, TIMEOUT);
        $previousStatus = $previousStates[$serverId] ?? 'Unknown';

        // บันทึกสถานะปัจจุบัน
        $currentStates[$serverId] = $currentStatus;

        writeLog("  → สถานะ: {$currentStatus}");

        // เก็บข้อมูลตามสถานะ
        if ($currentStatus === 'Offline') {
            $offlineServers[] = [
                'id' => $serverId,
                'name' => $serverName,
                'address' => $serverAddress
            ];
        } else {
            $onlineServers[] = [
                'id' => $serverId,
                'name' => $serverName,
                'address' => $serverAddress
            ];
        }

        // ตรวจสอบการเปลี่ยนสถานะ
        if ($previousStatus !== 'Unknown' && $previousStatus !== $currentStatus) {
            $statusChanged[] = [
                'id' => $serverId,
                'name' => $serverName,
                'address' => $serverAddress,
                'from' => $previousStatus,
                'to' => $currentStatus
            ];
        }
    }

    // บันทึกสถานะปัจจุบัน
    saveServerStates($currentStates);

    // ========== สร้างข้อความแจ้งเตือน ==========
    $needAlert = false;
    $message = "🔔 <b>รายงานสถานะเซิร์ฟเวอร์</b>\n";
    $message .= "📅 " . date('Y-m-d H:i:s') . "\n\n";

    // แจ้งเตือนเซิร์ฟเวอร์ที่เปลี่ยนสถานะ
    if (!empty($statusChanged)) {
        $needAlert = true;
        $message .= "⚠️ <b>เซิร์ฟเวอร์ที่เปลี่ยนสถานะ:</b>\n";
        foreach ($statusChanged as $server) {
            $emoji = $server['to'] === 'Offline' ? '🔴' : '🟢';
            $message .= "{$emoji} <b>{$server['name']}</b>\n";
            $message .= "   URL: {$server['address']}\n";
            $message .= "   {$server['from']} → {$server['to']}\n\n";
        }
    }

    // แจ้งเตือนเซิร์ฟเวอร์ที่ Offline
    if (!empty($offlineServers)) {
        $needAlert = true;
        $message .= "❌ <b>เซิร์ฟเวอร์ที่ Offline ({count}):</b>\n";
        $message = str_replace('{count}', count($offlineServers), $message);
        foreach ($offlineServers as $server) {
            $message .= "🔴 {$server['name']} - {$server['address']}\n";
        }
        $message .= "\n";
    }

    // สรุปสถานะ
    $totalServers = count($onlineServers) + count($offlineServers);
    $message .= "📊 <b>สรุป:</b>\n";
    $message .= "✅ Online: " . count($onlineServers) . " เซิร์ฟเวอร์\n";
    $message .= "❌ Offline: " . count($offlineServers) . " เซิร์ฟเวอร์\n";
    $message .= "📝 ทั้งหมด: {$totalServers} เซิร์ฟเวอร์";

    // ส่งแจ้งเตือนผ่าน Telegram
    if ($needAlert || count($offlineServers) > 0) {
        writeLog("กำลังส่งแจ้งเตือนผ่าน Telegram...");
        
        if (sendTelegramMessage($message)) {
            writeLog("✓ ส่งแจ้งเตือนสำเร็จ");
        } else {
            writeLog("✗ ส่งแจ้งเตือนไม่สำเร็จ");
        }
    } else {
        writeLog("ไม่มีการเปลี่ยนแปลงหรือปัญหา - ไม่ส่งแจ้งเตือน");
    }

    writeLog("========== ตรวจสอบเซิร์ฟเวอร์เสร็จสิ้น ==========\n");

} catch (Exception $e) {
    $errorMessage = "❌ <b>เกิดข้อผิดพลาด!</b>\n\n";
    $errorMessage .= "📅 " . date('Y-m-d H:i:s') . "\n";
    $errorMessage .= "⚠️ " . $e->getMessage();
    
    writeLog("ERROR: " . $e->getMessage());
    sendTelegramMessage($errorMessage);
}

// ปิดการเชื่อมต่อ Database
if (isset($conn)) {
    $conn->close();
}
?>