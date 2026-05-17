-- ============================================================
-- Migration: Thêm cột DaGuiNhacEmail vào bảng LichHen
-- Mục đích: Đánh dấu lịch hẹn đã được gửi email nhắc trước 1 tiếng
-- Cron job: bin/send_appointment_reminders.php
-- Ngày tạo: 2026
-- ============================================================

USE DERMASOFT;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE Name = N'DaGuiNhacEmail'
      AND Object_ID = Object_ID(N'dbo.LichHen')
)
BEGIN
    ALTER TABLE dbo.LichHen
        ADD DaGuiNhacEmail BIT NOT NULL CONSTRAINT DF_LichHen_DaGuiNhacEmail DEFAULT 0;
    PRINT N'Đã thêm cột DaGuiNhacEmail vào bảng LichHen.';
END
ELSE
BEGIN
    PRINT N'Cột DaGuiNhacEmail đã tồn tại — bỏ qua.';
END
GO

-- Index hỗ trợ cron query (tìm nhanh lịch sắp tới chưa gửi nhắc)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_LichHen_NhacEmail'
      AND object_id = OBJECT_ID(N'dbo.LichHen')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_LichHen_NhacEmail
        ON dbo.LichHen (ThoiGianHen, DaGuiNhacEmail, TrangThai);
    PRINT N'Đã tạo index IX_LichHen_NhacEmail.';
END
GO
