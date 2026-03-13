@echo off
REM ========================================
REM Server Check Runner for Task Scheduler
REM ========================================

REM กำหนดตำแหน่ง PHP
#SET PHP_PATH=C:\webserver\php\php.exe
SET PHP_PATH=C:\xampp\php\php.exe


REM กำหนดตำแหน่งไฟล์ PHP ที่ต้องการรัน
#SET SCRIPT_PATH=C:\xampp\htdocs\server_monitor\check_servers_telegram.php
SET SCRIPT_PATH=C:\webserver\www\myproject_ww\check_servers_telegram.php

REM แสดงข้อความเริ่มต้น
echo ========================================
echo Server Status Checker
echo %date% %time%
echo ========================================
echo.

REM รันไฟล์ PHP
"%PHP_PATH%" "%SCRIPT_PATH%"

REM แสดงสถานะการทำงาน
if %ERRORLEVEL% EQU 0 (
    echo.
    echo [SUCCESS] Script executed successfully
) else (
    echo.
    echo [ERROR] Script failed with error code %ERRORLEVEL%
)

REM รอ 5 วินาทีก่อนปิด (ถ้ารันด้วยตนเอง)
REM timeout /t 5 /nobreak

exit /b %ERRORLEVEL%