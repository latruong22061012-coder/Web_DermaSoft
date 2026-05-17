@echo off
REM ============================================================
REM Wrapper chạy reminder cron job
REM Dùng cho Windows Task Scheduler (chạy mỗi 5 phút)
REM ============================================================

REM Đường dẫn PHP (sửa lại nếu cần). XAMPP mặc định: C:\xampp\php\php.exe
set PHP_EXE=C:\xampp\php\php.exe

REM Đường dẫn script
set SCRIPT=%~dp0send_appointment_reminders.php

REM Chạy
"%PHP_EXE%" "%SCRIPT%"

exit /b %ERRORLEVEL%
