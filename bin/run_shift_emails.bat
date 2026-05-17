@echo off
REM ============================================================
REM Wrapper chạy shift email cron job
REM Dùng cho Windows Task Scheduler (gợi ý: mỗi 5 phút)
REM ============================================================

set PHP_EXE=C:\xampp\php\php.exe
set SCRIPT=%~dp0send_shift_assignment_emails.php

"%PHP_EXE%" "%SCRIPT%"

exit /b %ERRORLEVEL%
