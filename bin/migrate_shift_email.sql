-- ============================================================
-- Migration: Hỗ trợ gửi email khi PhanCongCa thay đổi
-- ----------------------------------------------------------------
-- Cron job: bin/send_shift_assignment_emails.php (chạy mỗi 5 phút)
--
-- Hạng mục:
--   1. Thêm cột PhanCongCa.DaGuiEmailPhanCong (BIT, mặc định 0)
--      → cron sẽ pick up các bản ghi MỚI (INSERT) hoặc bản ghi
--        bị reset cờ về 0 khi UPDATE (xem trigger #3).
--   2. Tạo bảng PhanCongCa_AuditEmail để bắt sự kiện XÓA
--      (vì sau khi DELETE, row gốc đã biến mất → cần snapshot).
--   3. Trigger AFTER UPDATE: reset cờ về 0 + ghi nhận ca cũ.
--   4. Trigger AFTER DELETE: ghi snapshot vào bảng audit.
--   5. Index hỗ trợ cron query.
--
-- Script idempotent: chạy lại an toàn.
-- ============================================================

USE DERMASOFT;
GO

-- ─────────────────────────────────────────────────────────────
-- 1) Cột cờ: đã gửi email phân công?
-- ─────────────────────────────────────────────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE Name = N'DaGuiEmailPhanCong'
      AND Object_ID = Object_ID(N'dbo.PhanCongCa')
)
BEGIN
    ALTER TABLE dbo.PhanCongCa
        ADD DaGuiEmailPhanCong BIT NOT NULL
        CONSTRAINT DF_PhanCongCa_DaGuiEmailPhanCong DEFAULT 0;
    PRINT N'Đã thêm cột DaGuiEmailPhanCong vào bảng PhanCongCa.';
END
ELSE
BEGIN
    PRINT N'Cột DaGuiEmailPhanCong đã tồn tại — bỏ qua.';
END
GO

-- ─────────────────────────────────────────────────────────────
-- 2) Bảng audit cho UPDATE/DELETE
--    LoaiThaoTac: 'SUA' | 'XOA'
--    Lưu thông tin cũ (deleted) và thông tin mới (inserted, nếu có).
-- ─────────────────────────────────────────────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.tables WHERE name = N'PhanCongCa_AuditEmail'
)
BEGIN
    CREATE TABLE dbo.PhanCongCa_AuditEmail (
        MaAudit         INT IDENTITY(1,1) PRIMARY KEY,
        LoaiThaoTac     NVARCHAR(10)  NOT NULL,           -- 'SUA' hoặc 'XOA'
        MaPhanCong      INT           NULL,               -- id bản ghi gốc (NULL nếu đã xóa hẳn)
        MaNguoiDung     INT           NOT NULL,           -- nhân viên bị ảnh hưởng
        -- Thông tin CŨ (trước khi sửa / trước khi xóa)
        MaCaCu          INT           NULL,
        NgayLamViecCu   DATE          NULL,
        -- Thông tin MỚI (chỉ có khi SUA)
        MaCaMoi         INT           NULL,
        NgayLamViecMoi  DATE          NULL,
        -- Quản lý gửi mail
        DaGuiEmail      BIT           NOT NULL CONSTRAINT DF_PCCAE_DaGuiEmail DEFAULT 0,
        NgayTao         DATETIME      NOT NULL CONSTRAINT DF_PCCAE_NgayTao    DEFAULT GETDATE()
    );
    PRINT N'Đã tạo bảng PhanCongCa_AuditEmail.';
END
ELSE
BEGIN
    PRINT N'Bảng PhanCongCa_AuditEmail đã tồn tại — bỏ qua.';
END
GO

-- Index cho cron quét audit chưa gửi
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_PCCAuditEmail_ChuaGui'
      AND object_id = OBJECT_ID(N'dbo.PhanCongCa_AuditEmail')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_PCCAuditEmail_ChuaGui
        ON dbo.PhanCongCa_AuditEmail (DaGuiEmail, NgayTao);
    PRINT N'Đã tạo index IX_PCCAuditEmail_ChuaGui.';
END
GO

-- Index cho cron quét PhanCongCa chưa gửi (cờ = 0)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = N'IX_PhanCongCa_ChuaGuiEmail'
      AND object_id = OBJECT_ID(N'dbo.PhanCongCa')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_PhanCongCa_ChuaGuiEmail
        ON dbo.PhanCongCa (DaGuiEmailPhanCong)
        INCLUDE (MaNguoiDung, MaCa, NgayLamViec);
    PRINT N'Đã tạo index IX_PhanCongCa_ChuaGuiEmail.';
END
GO

-- ─────────────────────────────────────────────────────────────
-- 3) Trigger AFTER UPDATE trên PhanCongCa
--    Khi MaCa / NgayLamViec / MaNguoiDung thay đổi:
--      - Ghi audit (LoaiThaoTac='SUA') với cả cũ & mới
--      - KHÔNG reset cờ DaGuiEmailPhanCong (giữ nguyên), vì email
--        SUA được gửi từ bảng audit, không phải từ cờ.
--    Trigger chỉ xử lý phần ca/ngày/người, bỏ qua khi chỉ
--    cập nhật DaGuiEmailPhanCong hay TrangThaiDiemDanh.
-- ─────────────────────────────────────────────────────────────
IF OBJECT_ID(N'dbo.trg_PhanCongCa_AfterUpdate_Email', N'TR') IS NOT NULL
    DROP TRIGGER dbo.trg_PhanCongCa_AfterUpdate_Email;
GO

CREATE TRIGGER dbo.trg_PhanCongCa_AfterUpdate_Email
ON dbo.PhanCongCa
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    -- Chỉ làm gì nếu một trong các cột "định danh ca" thay đổi
    IF NOT (UPDATE(MaCa) OR UPDATE(NgayLamViec) OR UPDATE(MaNguoiDung))
        RETURN;

    INSERT INTO dbo.PhanCongCa_AuditEmail
        (LoaiThaoTac, MaPhanCong, MaNguoiDung,
         MaCaCu, NgayLamViecCu, MaCaMoi, NgayLamViecMoi)
    SELECT
        N'SUA',
        i.MaPhanCong,
        i.MaNguoiDung,
        d.MaCa,
        d.NgayLamViec,
        i.MaCa,
        i.NgayLamViec
    FROM inserted i
    INNER JOIN deleted d ON i.MaPhanCong = d.MaPhanCong
    WHERE  ISNULL(i.MaCa,        -1) <> ISNULL(d.MaCa,        -1)
        OR ISNULL(i.NgayLamViec, '1900-01-01') <> ISNULL(d.NgayLamViec, '1900-01-01')
        OR ISNULL(i.MaNguoiDung, -1) <> ISNULL(d.MaNguoiDung, -1);
END
GO

PRINT N'Đã tạo/cập nhật trigger trg_PhanCongCa_AfterUpdate_Email.';
GO

-- ─────────────────────────────────────────────────────────────
-- 4) Trigger AFTER DELETE trên PhanCongCa
--    Lưu snapshot bản ghi đã xóa để cron còn biết mà gửi email.
-- ─────────────────────────────────────────────────────────────
IF OBJECT_ID(N'dbo.trg_PhanCongCa_AfterDelete_Email', N'TR') IS NOT NULL
    DROP TRIGGER dbo.trg_PhanCongCa_AfterDelete_Email;
GO

CREATE TRIGGER dbo.trg_PhanCongCa_AfterDelete_Email
ON dbo.PhanCongCa
AFTER DELETE
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.PhanCongCa_AuditEmail
        (LoaiThaoTac, MaPhanCong, MaNguoiDung,
         MaCaCu, NgayLamViecCu, MaCaMoi, NgayLamViecMoi)
    SELECT
        N'XOA',
        d.MaPhanCong,
        d.MaNguoiDung,
        d.MaCa,
        d.NgayLamViec,
        NULL,
        NULL
    FROM deleted d;
END
GO

PRINT N'Đã tạo/cập nhật trigger trg_PhanCongCa_AfterDelete_Email.';
GO

PRINT N'=== Migration hoàn tất ===';
GO
