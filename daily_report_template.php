<!DOCTYPE html>
<html>
<head>
    <style>
        /* CSS สำหรับ PDF โดยเฉพาะ */
        body { font-family: 'sarabun'; font-size: 14pt; color: #333; }
        
        /* หัวตาราง */
        h1 { font-size: 20pt; font-weight: bold; text-align: center; margin-bottom: 5px; }
        h3 { font-size: 16pt; text-align: center; margin-bottom: 20px; }
        
        /* ตาราง */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { 
            background-color: #f8f9fc; 
            border: 1px solid #000; 
            padding: 8px; 
            font-weight: bold;
            text-align: center;
            /* สำคัญ: ป้องกันหัวตารางฉีกตอนขึ้นหน้าใหม่ */
            vertical-align: middle;
        }
        td { 
            border: 1px solid #000; 
            padding: 6px; 
            vertical-align: top;
        }
        
        /* จัดตำแหน่งข้อความ */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        
        /* สีสลับบรรทัด (Zebra Striping) */
        tr:nth-child(even) { background-color: #fbfbfb; }

        /* Footer ท้ายกระดาษ */
        .footer-info { font-size: 10pt; color: #666; text-align: right; margin-top: 10px; }
    </style>
</head>
<body>

    <h1><?php echo $report_header['title']; ?></h1>
    <h3><?php echo $report_header['range']; ?></h3>

    <table>
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="15%">วันที่</th>
                <th width="40%">รายละเอียด</th>
                <th width="15%">จำนวน (ชิ้น)</th>
                <th width="15%">ยอดรวม (บาท)</th>
                <th width="10%">สถานะ</th>
            </tr>
        </thead>
        
        <tbody>
            <?php 
            $total_qty = 0;
            $total_amount = 0;
            $i = 1;

            if (count($data) > 0) {
                foreach ($data as $row) {
                    $total_qty += $row['qty']; // สมมติชื่อฟิลด์ qty
                    $total_amount += $row['total_price']; // สมมติชื่อฟิลด์ total_price
            ?>
                <tr>
                    <td class="text-center"><?php echo $i++; ?></td>
                    <td class="text-center"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                    <td class="text-left"><?php echo $row['title']; ?></td>
                    <td class="text-center"></td>
                    <td class="text-right"></td>
                    <td class="text-center"></td>
                </tr>
            <?php 
                } 
            } else {
            ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 20px;">ไม่พบข้อมูลในช่วงเวลาที่เลือก</td>
                </tr>
            <?php } ?>
            
            <tr style="background-color: #eeeeee;">
                <td colspan="3" class="text-right"><strong>รวมทั้งสิ้น</strong></td>
                <td class="text-center"><strong><?php echo number_format($total_qty); ?></strong></td>
                <td class="text-right"><strong><?php echo number_format($total_amount, 2); ?></strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="footer-info">
        พิมพ์เมื่อ: <?php echo $report_header['print_date']; ?> | โดย: <?php echo $_SESSION['username'] ?? 'System'; ?>
    </div>

</body>
</html>