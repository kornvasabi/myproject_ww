SHOW ENGINES;
-- ENGINES => FEDERATED เปิดการใช้งาน my.ini

-- สร้าง connect database old
CREATE SERVER old_server_link
FOREIGN DATA WRAPPER mysql
OPTIONS (
    HOST '192.168.0.18',
    DATABASE 'woodworkbarcode',
    USER 'connect_mt',
    PASSWORD 'p@ssword',  -- ใส่รหัสที่มี @ ได้เลยตรงนี้ ไม่ต้องกังวล
    PORT 3306
);

SELECT * FROM mysql.servers;

-- สร้าง table link จาก datbase old
CREATE TABLE wood_size_link (
   `id` BIGINT(20) NOT NULL,
   `grade` VARCHAR(255) NOT NULL,
   `is_display` BIT(1) NOT NULL,
   `length` INT(11) NOT NULL,
   `thick` INT(11) NOT NULL,
   `type_id` BIGINT(20) NOT NULL,
   `width` VARCHAR(255) NOT NULL,
   `wood_code` VARCHAR(255) NOT NULL,
   `mil` INT(11) NOT NULL,
   `ax_code` VARCHAR(255) NULL DEFAULT NULL,
   `is_active` BIT(1) NOT NULL,
   `is_special` BIT(1) NOT NULL,
   `pallet_quantity` INT(11) NOT NULL,
   `wage` DOUBLE NOT NULL,
   `wage_sorting_cut_wood` DOUBLE NOT NULL,
   KEY (`id`)
)
ENGINE=FEDERATED
DEFAULT CHARSET=utf8
CONNECTION='old_server_link/wood_size';


SELECT * FROM wood_size_link

-- สร้าง table เก็บข้อมูล
CREATE TABLE `wood_size` (
	`id` BIGINT(20) NOT NULL AUTO_INCREMENT,
	`grade` VARCHAR(255) NOT NULL COLLATE 'utf8mb3_general_ci',
	`is_display` BIT(1) NOT NULL,
	`length` INT(11) NOT NULL,
	`thick` INT(11) NOT NULL,
	`type_id` BIGINT(20) NOT NULL,
	`width` VARCHAR(255) NOT NULL COLLATE 'utf8mb3_general_ci',
	`wood_code` VARCHAR(255) NOT NULL COLLATE 'utf8mb3_general_ci',
	`mil` INT(11) NOT NULL,
	`ax_code` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8mb3_general_ci',
	`is_active` BIT(1) NOT NULL,
	`is_special` BIT(1) NOT NULL,
	`pallet_quantity` INT(11) NOT NULL,
	`wage` DOUBLE NOT NULL,
	`wage_sorting_cut_wood` DOUBLE NOT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `FK965AF653D50C8E48` (`type_id`) USING BTREE,
	INDEX `idx_id` (`id`) USING BTREE
)
COLLATE='utf8mb3_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=32142
;

-- ดึงข้อมูลมาเก็บ db ใหม่ถ้า id ซ้ำ ให้ Update ข้อมูล
INSERT INTO wood_size (
	id,
	grade,
	is_display,
	length,
	thick,
	type_id,
	width,
	wood_code,
	mil,
	ax_code,
	is_active,
	is_special,
	pallet_quantity,
	wage,
	wage_sorting_cut_wood
) -- ระบุชื่อคอลัมน์ให้ครบจะดีที่สุด
SELECT 	id,
	grade,
	is_display,
	length,
	thick,
	type_id,
	width,
	wood_code,
	mil,
	ax_code,
	is_active,
	is_special,
	pallet_quantity,
	wage,
	wage_sorting_cut_wood
FROM wood_size_link
ON DUPLICATE KEY UPDATE
    is_active = VALUES(is_active),
	 pallet_quantity = VALUES(pallet_quantity),
    wage = VALUES(wage),
    wage_sorting_cut_wood = VALUES(wage_sorting_cut_wood);

SELECT * from wood_size





