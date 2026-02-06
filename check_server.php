<?php
session_start();
// ตั้งค่า Root Path ให้ถูกต้องเพื่อป้องกันปัญหา Path ผิด
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/myproject_ww';
require_once $root_path . '/db.php';
require_once $root_path . '/includes/auth.php';

// ตรวจสิทธิ์การเข้าถึงหน้านี้
checkAccess($conn, basename($_SERVER['PHP_SELF'])); // เปิดบรรทัดนี้ถ้ามีฟังก์ชัน checkAccess
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>เช็คสถานะเซิร์ฟเวอร์</title>
    <link href="/myproject_ww/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="/myproject_ww/css/sb-admin-2.min.css" rel="stylesheet">
    
    <style>
        .card { background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); padding: 30px; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 10px; font-size: 28px; }
        .subtitle { color: #666; margin-bottom: 25px; font-size: 14px; }
        
        /* ปุ่มควบคุม */
        .controls { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 25px; }
        .button2 { padding: 12px 24px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        .btn-secondary2 { background: #e0e7ff; color: #667eea; }
        .btn-danger { background: #fee; color: #dc2626; }

        /* ส่วนแสดงสถานะ */
        .status-bar { display: flex; gap: 15px; padding: 15px; background: #f8fafc; border-radius: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .status-item { display: flex; align-items: center; gap: 8px; padding: 8px 15px; background: white; border-radius: 8px; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; animation: pulse 2s infinite; }
        .status-running { background: #22c55e; }
        .status-idle { background: #94a3b8; }
        .status-error { background: #ef4444; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

        /* Log Box */
        .log-container { background: #1e293b; border-radius: 10px; padding: 20px; max-height: 500px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; color: #e2e8f0; }
        .log-line { margin-bottom: 8px; white-space: pre-wrap; word-break: break-word; }
        .log-timestamp { color: #94a3b8; }
        .log-success { color: #86efac; }
        .log-error { color: #fca5a5; }
        .log-warning { color: #fcd34d; }
        .log-info { color: #7dd3fc; }
        .empty-log { text-align: center; color: #64748b; padding: 40px; }

        /* Cards สถิติ */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); padding: 15px; border-radius: 10px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: 700; color: #1e293b; margin-bottom: 5px; }
        .stat-label { font-size: 12px; color: #64748b; text-transform: uppercase; }

        /* ตัวเลือก Auto Run */
        .interval-control { display: flex; align-items: center; gap: 10px; padding: 15px; background: #f1f5f9; border-radius: 10px; margin-bottom: 20px; }
        .interval-control input, .interval-control select { padding: 8px 12px; border: 2px solid #cbd5e1; border-radius: 6px; }

        /* Local Spinner (ใช้เฉพาะปุ่ม Run) */
        .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; display: none; }
        .alert.show { display: block; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        
        <?php include($root_path . '/includes/sidebar.php'); ?>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                
                <?php include($root_path . '/includes/topbar.php'); ?>
                
                <div class="container-fluid">
                    <div class="card">
                        <h1>🖥️ Server Status Monitor Runner</h1>
                        <p class="subtitle">ระบบตรวจสอบสถานะเซิร์ฟเวอร์อัตโนมัติและแจ้งเตือนผ่าน Telegram</p>

                        <div id="alert" class="alert"></div>

                        <div class="stats">
                            <div class="stat-card">
                                <div class="stat-value" id="runCount">0</div>
                                <div class="stat-label">รอบที่ตรวจสอบ</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="successCount">0</div>
                                <div class="stat-label">สำเร็จ</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="errorCount">0</div>
                                <div class="stat-label">ผิดพลาด</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="lastRun">-</div>
                                <div class="stat-label">ตรวจสอบล่าสุด</div>
                            </div>
                        </div>

                        <div class="status-bar">
                            <div class="status-item">
                                <div class="status-dot status-idle" id="statusDot"></div>
                                <span id="statusText">พร้อมทำงาน</span>
                            </div>
                            <div class="status-item">
                                <span>⏱️ Timeout: <strong id="currentTimeout">30</strong>s</span>
                            </div>
                        </div>

                        <div class="interval-control">
                            <label>🔄 Auto Run:</label>
                            <input type="number" id="intervalValue" value="5" min="1" max="60">
                            <select id="intervalUnit">
                                <option value="60000">นาที</option>
                                <option value="1000">วินาที</option>
                            </select>
                            <button class="btn-secondary button2 btn-secondary2" onclick="startAutoRun()">
                                <span>▶️</span> เริ่ม Auto
                            </button>
                            <button class="btn-danger button2" onclick="stopAutoRun()" disabled id="stopBtn">
                                <span>⏹️</span> หยุด Auto
                            </button>
                        </div>

                        <div class="controls">
                            <button class="btn-primary button2" onclick="runCheck()" id="runBtn">
                                <span>▶️</span> Run ตรวจสอบ
                            </button>
                            <button class="btn-secondary button2 btn-secondary2" onclick="clearLog()">
                                <span>🗑️</span> Clear Log
                            </button>
                            <button class="btn-secondary button2 btn-secondary2" onclick="downloadLog()">
                                <span>💾</span> Save Log
                            </button>
                        </div>

                        <div class="log-container" id="logContainer">
                            <div class="empty-log">
                                📋 กด "Run ตรวจสอบ" เพื่อเริ่มตรวจสอบสถานะเซิร์ฟเวอร์
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include($root_path . '/includes/footer.php'); ?>
        </div>
    </div>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">คุณต้องการออกจากระบบใช่ไหม?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">เลือก "Logout" ด้านล่างหากคุณต้องการจบการทำงานในครั้งนี้</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="/myproject_ww/vendor/jquery/jquery.min.js"></script>
    <script src="/myproject_ww/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/myproject_ww/js/sb-admin-2.min.js"></script>

    <script>
        let autoRunInterval = null;
        let stats = { runCount: 0, successCount: 0, errorCount: 0 };
        
        window.addEventListener('load', () => {
            // ซ่อน Global Loader เผื่อกรณีที่สคริปต์ใน sidebar ไม่ได้สั่งปิด (กันเหนียวสำหรับหน้านี้)
            const loader = document.getElementById('global_loader');
            if (loader) {
                loader.classList.add('fade-out');
            }

            // เคลียร์ Interval เดิม ๆ ทั้งหมด (logic เดิม)
            const maxIntervalId = setInterval(() => {}, 0);
            for (let i = 1; i <= maxIntervalId; i++) { clearInterval(i); }
        });

        async function runCheck() {
            const runBtn = document.getElementById('runBtn');
            const statusDot = document.getElementById('statusDot');
            const statusText = document.getElementById('statusText');
            
            runBtn.disabled = true;
            // ใช้ .spinner (Local) ไม่ใช่ Global Loader
            runBtn.innerHTML = '<span class="spinner"></span> กำลังตรวจสอบ...';
            
            statusDot.className = 'status-dot status-running';
            statusText.textContent = 'กำลังตรวจสอบเซิร์ฟเวอร์...';

            const startTime = Date.now();
            addLog('info', '▶️ เริ่มการตรวจสอบเซิร์ฟเวอร์...');

            try {
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 30000);

                // เรียกไฟล์ Backend (ต้องมีไฟล์นี้จริงใน folder ww/)
                const response = await fetch('check_servers_telegram.php', {
                    method: 'GET',
                    signal: controller.signal,
                    cache: 'no-cache'
                });

                clearTimeout(timeout);
                const duration = ((Date.now() - startTime) / 1000).toFixed(2);

                if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);

                const result = await response.text();
                
                stats.runCount++;
                stats.successCount++;
                updateStats();

                addLog('success', `✅ ตรวจสอบสำเร็จ (${duration}s)`);
                addLog('info', '--- ผลลัพธ์ ---');
                
                result.split('\n').forEach(line => {
                    if (line.trim()) {
                        if (line.includes('ERROR') || line.includes('✗')) addLog('error', line);
                        else if (line.includes('✓') || line.includes('Online')) addLog('success', line);
                        else if (line.includes('Offline') || line.includes('⚠️')) addLog('warning', line);
                        else addLog('info', line);
                    }
                });

                showAlert('success', '✅ ตรวจสอบเซิร์ฟเวอร์สำเร็จ!');
                statusDot.className = 'status-dot status-idle';
                statusText.textContent = 'พร้อมทำงาน';

            } catch (error) {
                stats.runCount++;
                stats.errorCount++;
                updateStats();
                const duration = ((Date.now() - startTime) / 1000).toFixed(2);
                
                let msg = error.name === 'AbortError' ? `❌ Timeout (${duration}s)` : `❌ Error: ${error.message}`;
                addLog('error', msg);
                showAlert('error', msg);

                statusDot.className = 'status-dot status-error';
                statusText.textContent = 'เกิดข้อผิดพลาด';
                setTimeout(() => {
                    statusDot.className = 'status-dot status-idle';
                    statusText.textContent = 'พร้อมทำงาน';
                }, 3000);
            }

            runBtn.disabled = false;
            runBtn.innerHTML = '<span>▶️</span> Run ตรวจสอบ';
        }

        function addLog(type, message) {
            const logContainer = document.getElementById('logContainer');
            if (logContainer.querySelector('.empty-log')) logContainer.querySelector('.empty-log').remove();

            const timestamp = new Date().toLocaleString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const logLine = document.createElement('div');
            logLine.className = `log-line log-${type}`;
            logLine.innerHTML = `<span class="log-timestamp">[${timestamp}]</span> ${escapeHtml(message)}`;
            
            logContainer.appendChild(logLine);
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        function clearLog() {
            document.getElementById('logContainer').innerHTML = '<div class="empty-log">📋 Log ถูกล้างแล้ว</div>';
        }

        function downloadLog() {
            const lines = document.querySelectorAll('.log-line');
            if (lines.length === 0) { showAlert('error', '⚠️ ไม่มี Log ให้ดาวน์โหลด'); return; }

            let content = `Server Monitor Log\nCreated: ${new Date().toLocaleString('th-TH')}\n${'='.repeat(50)}\n\n`;
            lines.forEach(line => content += line.textContent + '\n');

            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = `monitor_${Date.now()}.log`; a.click();
            URL.revokeObjectURL(url);
        }

        function startAutoRun() {
            if (autoRunInterval) clearInterval(autoRunInterval);
            const interval = parseInt(document.getElementById('intervalValue').value);
            const unit = parseInt(document.getElementById('intervalUnit').value);
            
            autoRunInterval = setInterval(runCheck, interval * unit);
            
            document.getElementById('stopBtn').disabled = false;
            document.querySelector('.interval-control input').disabled = true;
            document.querySelector('.interval-control select').disabled = true;
            
            addLog('info', `🔄 เปิด Auto Run ทุก ${interval} ${unit === 60000 ? 'นาที' : 'วินาที'}`);
            showAlert('success', '✅ เปิด Auto Run สำเร็จ');
            runCheck();
        }

        function stopAutoRun() {
            if (autoRunInterval) { clearInterval(autoRunInterval); autoRunInterval = null; }
            document.getElementById('stopBtn').disabled = true;
            document.querySelector('.interval-control input').disabled = false;
            document.querySelector('.interval-control select').disabled = false;
            addLog('warning', '⏹️ หยุด Auto Run แล้ว');
        }

        function updateStats() {
            document.getElementById('runCount').textContent = stats.runCount;
            document.getElementById('successCount').textContent = stats.successCount;
            document.getElementById('errorCount').textContent = stats.errorCount;
            document.getElementById('lastRun').textContent = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
        }

        function showAlert(type, message) {
            const alert = document.getElementById('alert');
            alert.className = `alert alert-${type} show`;
            alert.textContent = message;
            setTimeout(() => alert.classList.remove('show'), 5000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        window.addEventListener('beforeunload', () => { if (autoRunInterval) clearInterval(autoRunInterval); });
    </script>
</body>
</html>