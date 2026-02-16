<?php
session_start();
require_once 'db_sync.php'; 

// เพิ่มทรัพยากรเพราะเราจะทำงานกับก้อนข้อมูลใหญ่
set_time_limit(300); 
ini_set('memory_limit', '512M');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sync_date'])) {
    
    $sync_date = $conn_local->real_escape_string($_POST['sync_date']);

    // 1. สร้างช่วงเวลา (Start & End)
    $start_time = $sync_date . " 00:00:00";
    $end_time   = $sync_date . " 23:59:59";

    // 2. Query ดึงข้อมูลจาก Server เก่า
    $sql_remote = "SELECT qc.scale_date_time,qc.transaction_type_id,qc.log_wood_eval_size_id,qc.saw_name,qc.saw_time_id,qc.ws_customer_id,qc.log_wood_type_id
			,bc.barcode_id AS barcode_no,wz.wood_code,swt.id AS saw_wood_type_id,wz.wage AS rage_wage,wsam.amount
			,ROUND((
			  (MID(wz.wood_code, 6, 1) + MID(wz.wood_code, 7, 1) / 8) * 
			  (CASE MID(wz.wood_code, 8, 1)
				  WHEN 'A' THEN 10 WHEN 'B' THEN 11 WHEN 'C' THEN 12 
				  WHEN 'D' THEN 13 WHEN 'E' THEN 14 WHEN 'F' THEN 15
				  WHEN 'G' THEN 16 WHEN 'H' THEN 17 WHEN 'I' THEN 18
				  WHEN 'J' THEN 19 WHEN 'K' THEN 20 
				  ELSE MID(wz.wood_code, 8, 1)
			  END) + 
			  MID(wz.wood_code, 9, 1) / 8
		  ) * (RIGHT(wz.wood_code, 3) / 100) * 0.0228 * wsam.amount, 4) AS foot
		  ,ROUND(((
			  (MID(wz.wood_code, 6, 1) + MID(wz.wood_code, 7, 1) / 8) * 
			  (CASE MID(wz.wood_code, 8, 1)
				  WHEN 'A' THEN 10 WHEN 'B' THEN 11 WHEN 'C' THEN 12 
				  WHEN 'D' THEN 13 WHEN 'E' THEN 14 WHEN 'F' THEN 15
				  WHEN 'G' THEN 16 WHEN 'H' THEN 17 WHEN 'I' THEN 18
				  WHEN 'J' THEN 19 WHEN 'K' THEN 20 
				  ELSE MID(wz.wood_code, 8, 1)
			  END) + 
			  MID(wz.wood_code, 9, 1) / 8
		  ) * (RIGHT(wz.wood_code, 3) / 100) * 0.0228 * wsam.amount) * wz.wage, 4) AS wage_total
		FROM (
			SELECT 
				lwqc.* ,rws.id AS rws_id ,rws.barcode_id
			from (
			   SELECT 
					DATE(scale_date_time) AS scale_date_time
					,transaction_type_id -- ,(SELECT name FROM transaction_type WHERE id = a.transaction_type_id) AS transaction_type_name
					,log_wood_eval_size_id -- , (SELECT name FROM choice_domain WHERE id = a.log_wood_eval_size_id) AS log_wood_eval_size_name
					,saw_name,saw_table
					,saw_time_id -- , (SELECT time_name FROM saw_time WHERE id = a.saw_time_id) AS saw_time_name
					-- ,LEFT(wood_source,12) AS comp_code
					,ws_customer_id -- , (SELECT name FROM truck_company WHERE id = a.ws_customer_id) AS comp_name
					-- , (SELECT code FROM truck_company WHERE id = a.ws_customer_id) AS comp_code2
					,log_wood_type_id -- , (SELECT name FROM choice_domain WHERE id = a.log_wood_type_id) AS log_wood_type_name
					,SUM(a.weight) AS net_weight
				FROM log_woodqc a 
				WHERE 1=1 
					and scale_date_time >= '$start_time' AND scale_date_time <= '$end_time'
				GROUP BY scale_date_time
					,transaction_type_id
					,log_wood_eval_size_id
					,saw_name
					,wood_source,saw_time_id
					,ws_customer_id
					,log_wood_type_id
			)lwqc
			inner join (
			   select 
				   rws.id
					 ,rws.barcode_id
				   ,rws.saw_table_id,rws.produce_date
				   ,isws.code,isws.saw_time_id
			   from raw_wood_source rws 
			   left join interface_saw_worker_set isws on isws.id = rws.interfaced_worker_set_id
			   WHERE 1=1
					and rws.produce_date >= '$start_time' AND rws.produce_date <= '$end_time'
			) rws on 1=1 
				and rws.produce_date = lwqc.scale_date_time
				and rws.code = lwqc.saw_name
			   and rws.saw_time_id = lwqc.saw_time_id 
		)qc
		inner join barcode bc 
			on bc.id = qc.barcode_id
		inner join mobile_raw_wood_data mrwd 
			on mrwd.raw_wood_source_id = qc.rws_id
		inner join mobile_raw_wood_data_wood_size_amount_map mrwdwsam 
			on mrwdwsam.mobile_raw_wood_data_amount_map_id = mrwd.id
		inner join wood_size_amount_map wsam 
			on wsam.id = mrwdwsam.wood_size_amount_map_id 
		inner join wood_size wz 
			on wz.id = wsam.wood_size_id 
		INNER JOIN saw_wood_type swt 
			ON swt.id = wsam.saw_wood_type_id
		WHERE 1 = 1 ";
    
    $result_remote = $conn_remote->query($sql_remote);

    if (!$result_remote || $result_remote->num_rows == 0) {
        $_SESSION['msg_status'] = 'warning'; 
        $_SESSION['msg_title'] = 'ไม่พบข้อมูล'; 
        $_SESSION['msg_text'] = "ไม่พบข้อมูลในช่วงเวลา $start_time ถึง $end_time";
        header("Location: import_server.php"); exit();
    }

    $conn_local->begin_transaction();

    try {
        // 3. เคลียร์ตาราง Staging
        $conn_local->query("TRUNCATE TABLE import_staging_qr");

        // 4. Bulk Insert ลง Staging
        $sql_prefix = "INSERT INTO import_staging_qr (
            scale_date_time, transaction_type_id, log_wood_eval_size_id, saw_name, saw_time_id, 
            ws_customer_id, log_wood_type_id, barcode_no, wood_code, saw_wood_type_id, 
            rage_wage, amount, foot, wage_total
        ) VALUES ";
        
        $values = [];
        $batch_size = 500;
        $counter = 0;

        while ($row = $result_remote->fetch_assoc()) {
            $clean = array_map(function($val) use ($conn_local) {
                return "'" . $conn_local->real_escape_string($val) . "'";
            }, $row);

            $values[] = "(" . implode(",", [
                $clean['scale_date_time'], $clean['transaction_type_id'], $clean['log_wood_eval_size_id'], 
                $clean['saw_name'], $clean['saw_time_id'], $clean['ws_customer_id'], 
                $clean['log_wood_type_id'], $clean['barcode_no'], $clean['wood_code'], 
                $clean['saw_wood_type_id'], $clean['rage_wage'], $clean['amount'], 
                $clean['foot'], $clean['wage_total']
            ]) . ")";

            $counter++;

            if (count($values) >= $batch_size) {
                $conn_local->query($sql_prefix . implode(",", $values));
                $values = [];
            }
        }
        
        if (!empty($values)) {
            $conn_local->query($sql_prefix . implode(",", $values));
        }

        // =========================================================
        // 5. ย้ายจาก Staging -> ตารางจริง (แบบ UPSERT: มีแล้ว Update / ไม่มี Insert)
        // =========================================================

        // 5.1 Insert/Update Main
        // ถ้า Barcode ซ้ำ -> ให้ Update ค่าอื่นๆ ทับไปเลย
        $sql_main = "INSERT INTO production_main 
                     (barcode_no, scale_date_time, transaction_type_id, log_wood_eval_size_id, saw_name, saw_time_id, ws_customer_id, log_wood_type_id)
                     SELECT DISTINCT 
                        barcode_no, scale_date_time, transaction_type_id, log_wood_eval_size_id, saw_name, saw_time_id, ws_customer_id, log_wood_type_id
                     FROM import_staging_qr 
                     WHERE barcode_no IS NOT NULL AND barcode_no != ''
                     ON DUPLICATE KEY UPDATE
                        scale_date_time = VALUES(scale_date_time),
                        transaction_type_id = VALUES(transaction_type_id),
                        log_wood_eval_size_id = VALUES(log_wood_eval_size_id),
                        saw_name = VALUES(saw_name),
                        saw_time_id = VALUES(saw_time_id),
                        ws_customer_id = VALUES(ws_customer_id),
                        log_wood_type_id = VALUES(log_wood_type_id)";
        
        if (!$conn_local->query($sql_main)) throw new Exception("Main Upsert Error: " . $conn_local->error);
        $count_main = $conn_local->affected_rows; // Note: ถ้า Update จะนับเป็น 2 rows

        // 5.2 Insert/Update Line
        // ต้องมี Unique Index (main_id, wood_code) ก่อน ถึงจะใช้คำสั่งนี้ได้
        $sql_line = "INSERT INTO production_line 
                     (main_id, wood_code, saw_wood_type_id, rage_wage, amount, foot, wage_total, price_total)
                     SELECT 
                        m.id, s.wood_code, s.saw_wood_type_id, s.rage_wage, s.amount, s.foot, s.wage_total,
                        s.foot * COALESCE(p.unit_price, 0)
                     FROM import_staging_qr s
                     JOIN production_main m ON s.barcode_no = m.barcode_no
                     LEFT JOIN product_prices p ON p.wood_code = s.wood_code 
                        AND DATE(s.scale_date_time) >= p.start_date 
                        AND DATE(s.scale_date_time) <= p.end_date
                     ON DUPLICATE KEY UPDATE
                        saw_wood_type_id = VALUES(saw_wood_type_id),
                        rage_wage = VALUES(rage_wage),
                        amount = VALUES(amount),
                        foot = VALUES(foot),
                        wage_total = VALUES(wage_total),
                        price_total = VALUES(price_total)";

        if (!$conn_local->query($sql_line)) throw new Exception("Line Upsert Error: " . $conn_local->error);
        $count_line = $conn_local->affected_rows;

        $conn_local->commit();
        
        $_SESSION['msg_status'] = 'success'; 
        $_SESSION['msg_title'] = 'Sync สำเร็จ!'; 
        $_SESSION['msg_text'] = "ดึงข้อมูล $counter แถว -> สร้าง Main: $count_main, Line: $count_line (คำนวณราคาเรียบร้อย)";

    } catch (Exception $e) {
        $conn_local->rollback();
        $_SESSION['msg_status'] = 'error'; 
        $_SESSION['msg_title'] = 'Sync ล้มเหลว'; 
        $_SESSION['msg_text'] = $e->getMessage();
    }

    header("Location: import_server.php");
    exit();
}
?>