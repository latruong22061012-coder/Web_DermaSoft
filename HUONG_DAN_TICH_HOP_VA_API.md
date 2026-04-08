 # BÁO CÁO HƯỚNG DẪN TÍCH HỢP WINDOWS APP ↔ WEBSITE

## Dự án Phòng Khám Da Liễu DERMASOFT

> **Ngày soạn:** 27/03/2026  
> **Phiên bản:** 1.0  
> **Mục đích:** Hướng dẫn team Windows App cách tích hợp dữ liệu với Website

---

## MỤC LỤC

- [Phần 1: Kiến trúc tổng quan](#phần-1-kiến-trúc-tổng-quan)
- [Phần 2: Windows App → Website (Đẩy dữ liệu lên)](#phần-2-windows-app--website-đẩy-dữ-liệu-lên)
- [Phần 3: Website → Windows App (Nhận dữ liệu về)](#phần-3-website--windows-app-nhận-dữ-liệu-về)
- [Phần 4: Tài liệu API đầy đủ](#phần-4-tài-liệu-api-đầy-đủ)
- [Phần 5: Cấu trúc Database chung](#phần-5-cấu-trúc-database-chung)

---

## PHẦN 1: KIẾN TRÚC TỔNG QUAN

### 1.1 Mô hình kết nối

```
┌──────────────────┐                                    ┌──────────────────┐
│   WINDOWS APP    │                                    │     WEBSITE      │
│   (C#/.NET)      │                                    │     (PHP)        │
│                  │                                    │                  │
│  • Form Lễ Tân   │         ┌──────────────┐           │  • Trang chủ     │
│  • Form Bác Sĩ   │ ◄─────► │  SQL Server  │ ◄───────► │  • Đặt lịch      │
│  • Form Admin    │  R/W    │  DERMASOFT   │   R/W    │  • Hồ sơ BN      │
│  • Form Thanh Toán│         └──────────────┘           │  • Xem kết quả   │
└──────────────────┘          (Cùng 1 Database)         └──────────────────┘
```

### 1.2 Thông tin kết nối Database

| Thông số                   | Giá trị                       |
| -------------------------- | ----------------------------- |
| **Server**                 | `localhost` (hoặc IP máy chủ) |
| **Database**               | `DERMASOFT`                   |
| **Authentication**         | SQL Server Authentication     |
| **Username**               | `sa`                          |
| **Password**               | `DarmaSoft2026`               |
| **Encrypt**                | `No`                          |
| **TrustServerCertificate** | `Yes`                         |

### 1.3 Connection String mẫu (C#)

```csharp
// Dành cho Windows App (C# / .NET)
string connectionString = @"Server=localhost;Database=DERMASOFT;User Id=sa;Password=DarmaSoft2026;TrustServerCertificate=True;Encrypt=False;";
```

### 1.4 Nguyên tắc quan trọng

> ⚠️ **CẢ HAI ỨNG DỤNG PHẢI KẾT NỐI ĐẾN CÙNG MỘT SQL SERVER INSTANCE**  
> Dữ liệu tự động đồng bộ vì dùng chung database. Không cần Message Queue hay WebSocket.

---

## PHẦN 2: WINDOWS APP → WEBSITE (Đẩy dữ liệu lên)

### 2.1 Tình huống: Bác sĩ tạo Phiếu Khám → Bệnh nhân xem trên Website

**Luồng xử lý:**

```
Windows App (Bác Sĩ)                SQL Server DERMASOFT              Website (Bệnh Nhân)
       │                                     │                              │
  [1]  │── INSERT PhieuKham ───────────────►  │                              │
       │── INSERT ChiTietDichVu ───────────►  │                              │
       │── INSERT ChiTietDonThuoc ──────────► │                              │
       │── INSERT HoaDon ──────────────────►  │                              │
       │                                     │                              │
       │                                     │  ◄── SELECT PhieuKham ───── [2]
       │                                     │  ◄── SELECT ChiTietDichVu ── │
       │                                     │  ◄── SELECT ChiTietDonThuoc─ │
       │                                     │  ◄── SELECT HoaDon ──────── │
       │                                     │                              │
       │                                     │  ──► JSON Response ────────► │
       │                                     │                     [3] Hiển thị chi tiết
```

**Bước 1 - Windows App INSERT dữ liệu:**

```sql
-- 1. Tạo Phiếu Khám
INSERT INTO PhieuKham (MaBenhNhan, MaLichHen, MaBacSi, NgayKham, TrieuChung, ChanDoan, HuongDieuTri, TrangThai)
VALUES (@MaBenhNhan, @MaLichHen, @MaBacSi, GETDATE(), N'Mụn trứng cá', N'Acne vulgaris', N'Dùng thuốc', 1);

-- Lấy MaPhieuKham vừa tạo
DECLARE @MaPK INT = SCOPE_IDENTITY();

-- 2. Thêm dịch vụ đã sử dụng
INSERT INTO ChiTietDichVu (MaPhieuKham, MaDichVu, SoLuong, DonGia, ThanhTien)
VALUES (@MaPK, 1, 1, 200000, 200000);

-- 3. Thêm đơn thuốc
INSERT INTO ChiTietDonThuoc (MaPhieuKham, MaThuoc, SoLuong, LieuDung, GhiChu)
VALUES (@MaPK, 1, 1, N'Bôi 2 lần/ngày', N'Tránh ánh nắng');

-- 4. Tạo hóa đơn
INSERT INTO HoaDon (MaPhieuKham, TongTienDichVu, TongThuoc, GiamGia, TongTien, PhuongThucThanhToan, TrangThai)
VALUES (@MaPK, 200000, 85000, 0, 285000, N'Tiền mặt', 1);
```

**Bước 2 & 3 - Website tự động đọc và hiển thị:**
Website gọi API `GET /api/phieukham/{id}` → trả về đầy đủ thông tin bao gồm dịch vụ, đơn thuốc, hóa đơn.

---

### 2.2 Tình huống: Lễ Tân thanh toán → Website hiện trạng thái

```sql
-- Windows App cập nhật thanh toán
UPDATE HoaDon
SET TrangThai = 1,                        -- 1 = Đã thanh toán
    TienKhachTra = @SoTienKhachTra,
    TienThua = @SoTienKhachTra - TongTien,
    PhuongThucThanhToan = N'Tiền mặt',
    NgayThanhToan = GETDATE()
WHERE MaHoaDon = @MaHoaDon;
```

Website sẽ tự động hiển thị trạng thái "Đã thanh toán" khi bệnh nhân refresh trang.

---

### 2.3 Tình huống: Lễ Tân xác nhận lịch hẹn

```sql
-- Windows App cập nhật trạng thái lịch hẹn
UPDATE LichHen
SET TrangThai = 1    -- 0=Chờ → 1=Xác nhận
WHERE MaLichHen = @MaLichHen;
```

Website sẽ hiển thị "Đã xác nhận" trong trang hồ sơ bệnh nhân.

---

## PHẦN 3: WEBSITE → WINDOWS APP (Nhận dữ liệu về)

### 3.1 Tình huống: Bệnh nhân đặt lịch trên Website → Lễ Tân xem trên Windows App

**Luồng xử lý:**

```
Website (Bệnh Nhân)                SQL Server DERMASOFT              Windows App (Lễ Tân)
       │                                     │                              │
  [1]  │── Gọi SP_DatLichHen ─────────────►  │                              │
       │   (INSERT LichHen + BenhNhan)       │                              │
       │                                     │                              │
       │                                     │  ◄── SELECT LichHen ───── [2] (Polling 15s)
       │                                     │                              │
       │                                     │  ──► DataTable ───────────► [3]
       │                                     │                     Hiển thị + Thông báo
```

**Bước 1 - Website INSERT lịch hẹn:**
Website gọi Stored Procedure `SP_DatLichHen` với tham số: HoTen, SoDienThoai, ThoiGianHen, GhiChu.  
SP tự động tạo BenhNhan mới (nếu chưa có) và tạo LichHen với TrangThai = 0 (Chờ xác nhận).

**Bước 2 - Windows App polling lấy lịch mới:**

```csharp
// ==========================================
// MẪU CODE WINDOWS APP - FORM LỄ TÂN
// ==========================================

public partial class FormLeTan : Form
{
    private System.Windows.Forms.Timer refreshTimer;
    private int lastPendingCount = 0;

    private void FormLeTan_Load(object sender, EventArgs e)
    {
        // Thiết lập Timer tự động refresh mỗi 15 giây
        refreshTimer = new System.Windows.Forms.Timer();
        refreshTimer.Interval = 15000; // 15 giây
        refreshTimer.Tick += RefreshTimer_Tick;
        refreshTimer.Start();

        // Load dữ liệu lần đầu
        LoadLichHenCho();
    }

    private void RefreshTimer_Tick(object sender, EventArgs e)
    {
        LoadLichHenCho();
    }

    private void LoadLichHenCho()
    {
        string query = @"
            SELECT
                lh.MaLichHen,
                lh.NgayHen,
                lh.ThoiGianHen,
                lh.GhiChu,
                lh.TrangThai,
                lh.NgayTao,
                bn.HoTen AS TenBenhNhan,
                bn.SoDienThoai,
                bn.Email
            FROM LichHen lh
            INNER JOIN BenhNhan bn ON lh.MaBenhNhan = bn.MaBenhNhan
            WHERE lh.TrangThai = 0          -- Chờ xác nhận
              AND lh.IsDeleted = 0
              AND bn.IsDeleted = 0
            ORDER BY lh.NgayHen ASC, lh.ThoiGianHen ASC";

        DataTable dt = ExecuteQuery(query);

        // Thông báo nếu có lịch hẹn MỚI
        if (dt.Rows.Count > lastPendingCount && lastPendingCount > 0)
        {
            int newCount = dt.Rows.Count - lastPendingCount;

            // Hiện thông báo cho Lễ Tân
            notifyIcon.ShowBalloonTip(
                5000,
                "📋 Lịch hẹn mới từ Website",
                $"Có {newCount} lịch hẹn mới cần xác nhận!",
                ToolTipIcon.Info
            );

            // Hoặc dùng MessageBox
            // MessageBox.Show($"Có {newCount} lịch hẹn mới!", "Thông báo");
        }

        lastPendingCount = dt.Rows.Count;

        // Cập nhật DataGridView
        dgvLichHen.DataSource = dt;
        lblTongCho.Text = $"Tổng chờ xác nhận: {dt.Rows.Count}";
    }

    // Lễ Tân xác nhận lịch hẹn
    private void btnXacNhan_Click(object sender, EventArgs e)
    {
        if (dgvLichHen.SelectedRows.Count == 0) return;

        int maLichHen = Convert.ToInt32(dgvLichHen.SelectedRows[0].Cells["MaLichHen"].Value);

        string sql = "UPDATE LichHen SET TrangThai = 1 WHERE MaLichHen = @MaLH";
        // Thực thi...

        LoadLichHenCho(); // Refresh
        MessageBox.Show("Đã xác nhận lịch hẹn!", "Thành công");
    }

    // Lễ Tân hủy lịch hẹn
    private void btnHuy_Click(object sender, EventArgs e)
    {
        if (dgvLichHen.SelectedRows.Count == 0) return;

        int maLichHen = Convert.ToInt32(dgvLichHen.SelectedRows[0].Cells["MaLichHen"].Value);

        string sql = "UPDATE LichHen SET TrangThai = 3 WHERE MaLichHen = @MaLH";
        // Thực thi...

        LoadLichHenCho(); // Refresh
    }
}
```

### 3.2 Bảng trạng thái cần đồng bộ

| Bảng          | TrangThai | Ý nghĩa         | Ai tạo           | Ai cập nhật      |
| ------------- | --------- | --------------- | ---------------- | ---------------- |
| **LichHen**   | 0         | Chờ xác nhận    | Website / WinApp | -                |
| **LichHen**   | 1         | Đã xác nhận     | -                | WinApp (Lễ Tân)  |
| **LichHen**   | 2         | Hoàn thành      | -                | WinApp (Bác Sĩ)  |
| **LichHen**   | 3         | Đã hủy          | Website / WinApp | Website / WinApp |
| **PhieuKham** | 0         | Chờ xử lý       | WinApp (Bác Sĩ)  | -                |
| **PhieuKham** | 1         | Hoàn thành      | -                | WinApp (Bác Sĩ)  |
| **PhieuKham** | 2         | Hủy             | -                | WinApp           |
| **HoaDon**    | 0         | Chưa thanh toán | WinApp           | -                |
| **HoaDon**    | 1         | Đã thanh toán   | -                | WinApp (Lễ Tân)  |

---

## PHẦN 4: TÀI LIỆU API ĐẦY ĐỦ

### 4.0 Thông tin chung

| Thông số            | Giá trị                                    |
| ------------------- | ------------------------------------------ |
| **Base URL**        | `http://localhost:3000/DarmaSoft/api/`     |
| **Content-Type**    | `application/json; charset=utf-8`          |
| **Authentication**  | Session-based (Cookie) hoặc Token (Header) |
| **Response Format** | JSON chuẩn (xem bên dưới)                  |

#### Cấu trúc Response chuẩn

```json
{
    "status": 200,
    "data": { ... },
    "message": "Thông báo tiếng Việt"
}
```

#### Mã trạng thái HTTP

| Mã    | Ý nghĩa               |
| ----- | --------------------- |
| `200` | Thành công            |
| `201` | Tạo mới thành công    |
| `400` | Dữ liệu không hợp lệ  |
| `401` | Chưa đăng nhập        |
| `403` | Không có quyền        |
| `404` | Không tìm thấy        |
| `409` | Dữ liệu trùng lặp     |
| `429` | Gửi quá nhiều yêu cầu |
| `500` | Lỗi server            |

---

### 4.1 XÁC THỰC (Auth)

#### `POST /api/auth/login` — Đăng nhập

**Request Body:**

```json
{
  "username": "string (>=3 ký tự)",
  "password": "string (>=6 ký tự)"
}
```

**Response (200):**

```json
{
  "status": 200,
  "data": {
    "user": {
      "id": 5,
      "name": "La Văn Trường",
      "username": "truong",
      "email": "truong@gmail.com",
      "phone": "0901234567",
      "role_id": 4
    },
    "token": "abc123...",
    "need_password_change": false
  },
  "message": "Đăng nhập thành công"
}
```

**Lỗi có thể xảy ra:** `400` (thiếu trường), `401` (sai mật khẩu), `403` (tài khoản bị khóa)

---

#### `POST /api/auth/logout` — Đăng xuất

**Yêu cầu:** Đã đăng nhập  
**Request Body:** Không cần  
**Response (200):** `{ "message": "Đăng xuất thành công" }`

---

#### `GET /api/auth/me` — Lấy thông tin user hiện tại

**Yêu cầu:** Đã đăng nhập  
**Response (200):**

```json
{
  "status": 200,
  "data": {
    "MaNguoiDung": 5,
    "HoTen": "La Văn Trường",
    "TenDangNhap": "truong",
    "Email": "truong@gmail.com",
    "SoDienThoai": "0901234567",
    "MaVaiTro": 4,
    "AnhDaiDien": "public/assets/images/avatars/avatar_5.jpg"
  },
  "message": "Lấy thông tin thành công"
}
```

---

#### `POST /api/auth/register` — Đăng ký tài khoản

**Request Body:**

```json
{
  "hoten": "Nguyễn Văn A",
  "sodienthoai": "0901234567",
  "email": "a@gmail.com",
  "tendangnhap": "nguyenvana",
  "matkhau": "123456"
}
```

**Response (201):** `{ "data": { "user_id": 10 } }`  
**Lỗi:** `409` (username đã tồn tại)

---

#### `POST /api/auth/change-password` — Đổi mật khẩu

**Yêu cầu:** Đã đăng nhập  
**Request Body:**

```json
{
  "old_password": "matkhau_cu",
  "new_password": "matkhau_moi"
}
```

**Response (200):** `{ "message": "Đổi mật khẩu thành công" }`

---

#### `POST /api/auth/verify-token` — Xác minh Token

**Request Body:**

```json
{
  "token": "abc123..."
}
```

**Response (200):**

```json
{
  "data": {
    "user_id": 5,
    "name": "La Văn Trường",
    "valid": true
  }
}
```

---

#### `POST /api/auth/forgot-password` — Quên mật khẩu

**Request Body:** `{ "email": "a@gmail.com" }`  
**Response (200):** `{ "message": "Nếu email tồn tại, hướng dẫn đã được gửi" }`

---

### 4.2 XÁC THỰC OTP/EMAIL

#### `POST /api/auth/check-phone` — Kiểm tra SĐT đã đăng ký chưa

**Request Body:** `{ "sodienthoai": "0901234567" }`  
**Response (200):**

```json
{
  "data": {
    "exists": true,
    "hoTen": "Nguyễn Văn A"
  }
}
```

---

#### `POST /api/auth/send-otp-login` — Gửi OTP đăng nhập qua Email

**Request Body:** `{ "sodienthoai": "0901234567" }`  
**Response (200):**

```json
{
  "data": {
    "expires_in": 300,
    "email_masked": "la****g@gmail.com"
  },
  "message": "Mã OTP đã gửi qua email"
}
```

**Lỗi:** `404` (SĐT không tồn tại), `429` (gửi quá nhiều lần)

---

#### `POST /api/auth/login-with-otp` — Đăng nhập bằng OTP

**Request Body:**

```json
{
  "sodienthoai": "0901234567",
  "otp": "123456"
}
```

**Response (200):** Giống response của `/api/auth/login`

---

#### `POST /api/auth/register-phone` — Đăng ký + gửi email xác minh

**Request Body:**

```json
{
  "hoTen": "Nguyễn Văn A",
  "sodienthoai": "0901234567",
  "email": "a@gmail.com",
  "matkhau": "123456"
}
```

**Response (201):**

```json
{
  "data": {
    "user_id": 10,
    "username": "0901234567",
    "verify_token": "abc..."
  }
}
```

---

#### `POST /api/auth/verify-email-token` — Xác minh Email

**Request Body:** `{ "token": "abc..." }`  
**Response (200):** `{ "data": { "user_id": 10, "verified": true } }`

---

#### `POST /api/auth/forgot-phone` — Quên SĐT (gửi qua email)

**Request Body:** `{ "email": "a@gmail.com" }`  
**Response (200):** `{ "message": "Thông tin đã gửi qua email" }`

---

#### `POST /api/auth/send-otp-phone-reset` — Gửi OTP đặt lại SĐT

**Request Body:** `{ "email": "a@gmail.com" }`  
**Response (200):**

```json
{
  "data": {
    "email_masked": "a****m@gmail.com",
    "expires_in": 300
  }
}
```

---

#### `POST /api/auth/reset-phone-with-otp` — Đặt lại SĐT bằng OTP

**Request Body:**

```json
{
  "email": "a@gmail.com",
  "otp": "123456",
  "phone_moi": "0909876543"
}
```

**Response (200):** `{ "message": "Cập nhật số điện thoại thành công" }`

---

#### `POST /api/auth/update-phone` — Cập nhật SĐT (đã đăng nhập)

**Yêu cầu:** Đã đăng nhập  
**Request Body:**

```json
{
  "sodienthoai_moi": "0909876543",
  "email_moi": "new@gmail.com",
  "otp_confirm": "123456"
}
```

---

### 4.3 BỆNH NHÂN (BenhNhan)

#### `GET /api/benhnhan` — Danh sách bệnh nhân

**Yêu cầu:** Đã đăng nhập  
**Query Params:** `?page=1&limit=20`  
**Response (200):**

```json
{
  "data": {
    "items": [
      {
        "MaBenhNhan": 1,
        "HoTen": "Nguyễn Văn A",
        "NgaySinh": "1990-01-15",
        "GioiTinh": "Nam",
        "SoDienThoai": "0901234567",
        "Email": "a@gmail.com",
        "DiaChi": "TP.HCM"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 50,
      "total_pages": 3
    }
  }
}
```

---

#### `GET /api/benhnhan/{id}` — Chi tiết bệnh nhân

**Response (200):** Object bệnh nhân đầy đủ

---

#### `GET /api/benhnhan/search?q={keyword}` — Tìm kiếm bệnh nhân

**Query Params:** `?q=Nguyễn` (tối thiểu 2 ký tự)  
**Response (200):** Mảng bệnh nhân khớp (tìm theo tên + SĐT)

---

#### `GET /api/benhnhan/phone/{phone}` — Tìm theo SĐT

**Response (200):** Object bệnh nhân hoặc `404`

---

#### `POST /api/benhnhan` — Tạo bệnh nhân mới

**Request Body:**

```json
{
  "hoten": "Nguyễn Văn B",
  "ngaysinh": "1995-06-20",
  "sodienthoai": "0908765432",
  "gioitinh": "Nam",
  "diachi": "Quận 1, TP.HCM",
  "email": "b@gmail.com"
}
```

**Response (201):** Object bệnh nhân vừa tạo  
**Lỗi:** `409` (SĐT đã tồn tại)

---

#### `PUT /api/benhnhan/{id}` — Cập nhật bệnh nhân

**Request Body:** Các trường cần cập nhật (hoten, ngaysinh, gioitinh, diachi, sodienthoai, email)  
**Response (200):** Object bệnh nhân đã cập nhật

---

#### `GET /api/benhnhan/{id}/appointments` — Lịch hẹn của bệnh nhân

**Response (200):** Mảng các lịch hẹn

---

#### `GET /api/benhnhan/{id}/consultations` — Phiếu khám của bệnh nhân

**Response (200):** Mảng các phiếu khám

---

#### `GET /api/benhnhan/{id}/invoices` — Hóa đơn của bệnh nhân

**Response (200):** Mảng các hóa đơn

---

#### `DELETE /api/benhnhan/{id}` — Xóa bệnh nhân

**Yêu cầu:** Admin (MaVaiTro = 1)  
**Response (200):** `{ "message": "Xóa thành công" }`

---

### 4.4 LỊCH HẸN (LichHen)

#### `GET /api/lichhens` — Danh sách lịch hẹn

**Query Params:** `?page=1&limit=20`  
**Response (200):** Danh sách phân trang (giống format BenhNhan)

---

#### `GET /api/lichhens/{id}` — Chi tiết lịch hẹn

**Response (200):**

```json
{
  "data": {
    "MaLichHen": 1,
    "MaBenhNhan": 5,
    "NgayHen": "2026-03-28",
    "ThoiGianHen": "2026-03-28 09:00",
    "GhiChu": "Tái khám mụn",
    "TrangThai": 0,
    "NgayTao": "2026-03-27 14:30:00"
  }
}
```

---

#### `GET /api/lichhens/patient/{id}` — Lịch hẹn theo bệnh nhân

**Response (200):** Mảng lịch hẹn của bệnh nhân

---

#### `GET /api/lichhens/status/{status}` — Lịch hẹn theo trạng thái

**Giá trị status:** `0` = Chờ, `1` = Xác nhận, `2` = Hoàn thành, `3` = Hủy  
**Response (200):** Mảng lịch hẹn

---

#### `GET /api/lichhens/pending` — Lịch hẹn chờ xác nhận

**Response (200):** Mảng lịch hẹn TrangThai = 0

---

#### `POST /api/lichhens` — Tạo lịch hẹn (nội bộ)

**Request Body:**

```json
{
  "ma_benhnhan": 5,
  "ma_bacsi": 2,
  "thoigian_hen": "2026-03-28 09:00",
  "ghi_chu": "Tái khám"
}
```

**Response (201):** Object lịch hẹn vừa tạo

---

#### `POST /api/booking/create` — Đặt lịch (Website công khai)

> ⭐ **Endpoint chính cho Website đặt lịch** — Không cần đăng nhập

**Request Body:**

```json
{
  "hoTen": "Nguyễn Văn C",
  "soDienThoai": "0907654321",
  "thoiGianHen": "2026-03-28 09:00",
  "ghiChu": "Khám mụn lần đầu"
}
```

**Validation:**

- SĐT phải đúng format VN: `^(0)(3[2-9]|5[6-9]|7[06-9]|8[0-9]|9[0-9])[0-9]{7}$`
- Thời gian phải trong tương lai, tối đa 60 ngày
- Không được đặt trùng ngày cho cùng SĐT

**Response (201):**

```json
{
  "data": {
    "maLichHen": 15,
    "maBenhNhan": 8,
    "thoiGianHen": "28/03/2026 09:00"
  },
  "message": "Đặt lịch thành công!"
}
```

**Stored Procedure:** `SP_DatLichHen` (tự tạo BenhNhan nếu SĐT chưa tồn tại)

---

#### `PUT /api/lichhens/{id}` — Cập nhật lịch hẹn

**Request Body:** Các trường cần cập nhật  
**Response (200):** Object lịch hẹn đã cập nhật

---

#### `PUT /api/lichhens/{id}/status` — Cập nhật trạng thái

**Request Body:** `{ "status": 1 }`  
**Response (200):** Object lịch hẹn đã cập nhật

---

#### `POST /api/lichhens/{id}/confirm` — Xác nhận lịch hẹn

**Response (200):** Object lịch hẹn (TrangThai → 1)

---

#### `POST /api/lichhens/{id}/cancel` — Hủy lịch hẹn

**Quyền:** Chỉ bệnh nhân sở hữu lịch hẹn, chỉ hủy khi TrangThai = 0  
**Response (200):** `{ "message": "Hủy lịch hẹn thành công" }`

---

### 4.5 PHIẾU KHÁM (PhieuKham)

#### `GET /api/phieukham` — Danh sách phiếu khám

**Query Params:** `?page=1&limit=20`  
**Response (200):** Danh sách phân trang

---

#### `GET /api/phieukham/{id}` — Chi tiết phiếu khám

> ⭐ **Endpoint quan trọng** — Trả đầy đủ thông tin

**Response (200):**

```json
{
  "data": {
    "MaPhieuKham": 6,
    "MaBenhNhan": 3,
    "MaLichHen": 5,
    "MaBacSi": 2,
    "TenBacSi": "BS. Nguyễn Thị Thanh Hương",
    "NgayKham": "2026-03-25",
    "TrieuChung": "Da mặt nổi mụn vùng chữ T",
    "ChanDoan": "Acne vulgaris độ II",
    "HuongDieuTri": "Dùng thuốc bôi + uống kháng sinh",
    "NgayHenTaiKham": "2026-04-08",
    "TrangThai": 1,
    "services": [
      {
        "MaDichVu": 1,
        "TenDichVu": "Khám tổng quát da liễu",
        "SoLuong": 1,
        "DonGia": 200000,
        "ThanhTien": 200000
      },
      {
        "MaDichVu": 3,
        "TenDichVu": "Điều trị mụn chuyên sâu",
        "SoLuong": 1,
        "DonGia": 350000,
        "ThanhTien": 350000
      }
    ],
    "medicines": [
      {
        "MaThuoc": 1,
        "TenThuoc": "Tretinoin 0.05%",
        "SoLuong": 1,
        "LieuDung": "Bôi tối 1 lần/ngày",
        "DonGia": 85000,
        "ThanhTien": 85000,
        "GhiChu": "Tránh ánh nắng trực tiếp"
      },
      {
        "MaThuoc": 2,
        "TenThuoc": "Clindamycin 1%",
        "SoLuong": 2,
        "LieuDung": "Bôi 2 lần/ngày sáng-tối",
        "DonGia": 65000,
        "ThanhTien": 130000,
        "GhiChu": ""
      }
    ],
    "hoaDon": {
      "MaHoaDon": 1,
      "TongTienDichVu": 550000,
      "TongThuoc": 315000,
      "GiamGia": 0,
      "TongTien": 865000,
      "TienKhachTra": 900000,
      "TienThua": 35000,
      "PhuongThucThanhToan": "Tiền mặt",
      "NgayThanhToan": "2026-03-25 10:30:00",
      "TrangThai": 1
    }
  }
}
```

---

#### `GET /api/phieukham/patient/{id}` — Phiếu khám theo bệnh nhân

**Response (200):** Mảng phiếu khám

---

#### `GET /api/phieukham/appointment/{id}` — Phiếu khám theo lịch hẹn

**Response (200):** Object phiếu khám + services + medicines

---

#### `POST /api/phieukham` — Tạo phiếu khám

**Request Body:**

```json
{
  "ma_benhnhan": 5,
  "ma_lichhhen": 10,
  "ma_bacsi": 2
}
```

**Response (201):** Object phiếu khám vừa tạo

---

#### `PUT /api/phieukham/{id}` — Cập nhật kết quả khám

**Request Body:**

```json
{
  "trieuchung": "Da mặt nổi mụn",
  "chandoan": "Acne vulgaris độ II",
  "huongdieutri": "Dùng thuốc bôi kết hợp uống",
  "ngayhen_taikham": "2026-04-08"
}
```

**Response (200):** Object phiếu khám đã cập nhật

---

#### `PUT /api/phieukham/{id}/services` — Thêm dịch vụ vào phiếu khám

**Request Body:**

```json
{
  "services": [
    { "ma_dichvu": 1, "soluong": 1, "thanhtien": 200000 },
    { "ma_dichvu": 3, "soluong": 1, "thanhtien": 350000 }
  ]
}
```

**Response (200):** Object phiếu khám + danh sách dịch vụ

---

#### `GET /api/phieukham/{id}/services` — Lấy dịch vụ của phiếu khám

**Response (200):** Mảng dịch vụ

---

#### `GET /api/phieukham/{id}/medicines` — Lấy đơn thuốc của phiếu khám

**Response (200):** Mảng thuốc (có DonGia và ThanhTien)

---

### 4.6 HÓA ĐƠN (HoaDon)

#### `GET /api/hoadon` — Danh sách hóa đơn

**Query Params:** `?page=1&limit=20`  
**Response (200):** Danh sách phân trang

---

#### `GET /api/hoadon/{id}` — Chi tiết hóa đơn

**Response (200):**

```json
{
  "data": {
    "MaHoaDon": 1,
    "MaPhieuKham": 6,
    "TongTienDichVu": 550000,
    "TongThuoc": 315000,
    "GiamGia": 0,
    "TongTien": 865000,
    "TienKhachTra": 900000,
    "TienThua": 35000,
    "PhuongThucThanhToan": "Tiền mặt",
    "NgayThanhToan": "2026-03-25 10:30:00",
    "TrangThai": 1,
    "NgayTao": "2026-03-25 09:00:00"
  }
}
```

---

#### `GET /api/hoadon/patient/{id}` — Hóa đơn theo bệnh nhân

**Response (200):** Mảng hóa đơn

---

#### `GET /api/hoadon/unpaid` — Hóa đơn chưa thanh toán

**Response (200):** Mảng hóa đơn TrangThai = 0

---

#### `GET /api/hoadon/paid` — Hóa đơn đã thanh toán

**Response (200):** Mảng hóa đơn TrangThai = 1

---

#### `POST /api/hoadon` — Tạo hóa đơn

**Request Body:**

```json
{
  "ma_phieukham": 6,
  "ma_benhnhan": 5,
  "ma_bacsi": 2,
  "tongtien": 865000,
  "ghichu": ""
}
```

**Response (201):** Object hóa đơn vừa tạo

---

#### `PUT /api/hoadon/{id}/payment` — Cập nhật thanh toán

**Request Body:**

```json
{
  "sotienphat": 900000,
  "phuongthuc": "Tiền mặt",
  "ghichu": ""
}
```

**Response (200):** Object hóa đơn đã cập nhật (tự tính TienThua)

---

#### `GET /api/hoadon/revenue/total` — Tổng doanh thu

**Query Params:** `?from=2026-01-01&to=2026-12-31`  
**Response (200):**

```json
{
  "data": {
    "total_revenue": 15000000,
    "from": "2026-01-01",
    "to": "2026-12-31"
  }
}
```

---

#### `GET /api/hoadon/revenue/daily` — Doanh thu theo ngày

**Query Params:** `?date=2026-03-27`  
**Response (200):** `{ "data": { "date": "2026-03-27", "revenue": 2500000 } }`

---

#### `DELETE /api/hoadon/{id}` — Xóa hóa đơn

**Yêu cầu:** Admin (MaVaiTro = 1)

---

### 4.7 ĐÁNH GIÁ DỊCH VỤ (DanhGia)

#### `POST /api/danhgia` — Gửi đánh giá

**Yêu cầu:** Đã đăng nhập (Bệnh nhân)  
**Request Body:**

```json
{
  "maPhieuKham": 6,
  "diemDanh": 5,
  "nhanXet": "Bác sĩ rất tận tình, phòng khám sạch sẽ"
}
```

**Validation:**

- `diemDanh`: 1-5 (bắt buộc)
- Phiếu khám phải thuộc bệnh nhân đang đăng nhập
- Phiếu khám phải hoàn thành (TrangThai = 1)
- Mỗi phiếu khám chỉ được đánh giá 1 lần

**Response (201):** `{ "message": "Đánh giá thành công" }`

---

#### `GET /api/danhgia/check/{maPhieuKham}` — Kiểm tra đã đánh giá chưa

**Yêu cầu:** Đã đăng nhập  
**Response (200):**

```json
{
  "data": {
    "hasRated": true,
    "review": {
      "MaDanhGia": 1,
      "DiemDanh": 5,
      "NhanXet": "Rất hài lòng",
      "NgayDanhGia": "2026-03-26 15:00:00"
    }
  }
}
```

---

### 4.8 THÀNH VIÊN (ThanhVienInfo)

#### `GET /api/thanh-vien` — Danh sách thành viên

**Query Params:** `?page=1&limit=20`  
**Response (200):** Danh sách phân trang

---

#### `GET /api/thanh-vien/{id}` — Chi tiết thành viên + hạng

**Response (200):**

```json
{
  "data": {
    "MaThanhVien": 1,
    "MaBenhNhan": 5,
    "MaHangThanhVien": 2,
    "DiemThuong": 6500,
    "TongChiTieu": 1500000,
    "TyLeHaiLong": 4.5,
    "NgayDangKy": "2026-01-15",
    "tier": {
      "MaHangThanhVien": 2,
      "TenHang": "Bạc",
      "DiemToiThieu": 300,
      "MucGiamGia": 5,
      "MoTaUuDai": "Giảm 5% dịch vụ"
    }
  }
}
```

---

#### `GET /api/thanh-vien/patient/{id}` — Thông tin thành viên theo bệnh nhân

**Response (200):** Object thành viên + hạng (hoặc null nếu chưa đăng ký)

---

#### `POST /api/thanh-vien` — Đăng ký thành viên

**Request Body:** `{ "ma_benhnhan": 5 }`  
**Response (201):** Object thành viên (hạng Đồng mặc định, 0 điểm)

---

#### `PUT /api/thanh-vien/{id}` — Cập nhật thông tin thành viên

**Request Body:** Các trường cần cập nhật  
**Response (200):** Object thành viên đã cập nhật

---

#### `PUT /api/thanh-vien/{id}/points` — Cộng điểm thưởng

**Request Body:**

```json
{
  "points_add": 500,
  "reason": "Thanh toán dịch vụ"
}
```

**Tự động nâng hạng:**

- Đồng: 0 - 4,999 điểm (MaHangThanhVien = 1)
- Bạc: 5,000 - 9,999 điểm (MaHangThanhVien = 2)
- Vàng: 10,000+ điểm (MaHangThanhVien = 3)

**Response (200):** Object thành viên đã cập nhật + hạng mới

---

#### `GET /api/thanh-vien/{id}/points` — Xem điểm + hạng

**Response (200):**

```json
{
  "data": {
    "member_id": 1,
    "points": 6500,
    "tier": { "TenHang": "Bạc", "MucGiamGia": 5 },
    "registered_date": "2026-01-15"
  }
}
```

---

#### `GET /api/thanh-vien/tiers` — Danh sách tất cả hạng thành viên

**Response (200):** Mảng hạng thành viên

---

#### `GET /api/thanh-vien/top-points?limit=10` — Top thành viên nhiều điểm nhất

**Response (200):** Mảng thành viên sắp xếp theo DiemThuong giảm dần

---

#### `DELETE /api/thanh-vien/{id}` — Xóa thành viên

**Yêu cầu:** Admin (MaVaiTro = 1)

---

### 4.9 HỒ SƠ CÁ NHÂN (Profile)

#### `POST /api/profile/update` — Cập nhật thông tin cá nhân

**Yêu cầu:** Đã đăng nhập  
**Request Body:**

```json
{
  "hoTen": "La Văn Trường",
  "email": "truong_new@gmail.com"
}
```

**Response (200):** `{ "data": { "hoTen": "La Văn Trường", "email": "truong_new@gmail.com" } }`

---

#### `POST /api/profile/upload-avatar` — Upload ảnh đại diện

**Yêu cầu:** Đã đăng nhập  
**Content-Type:** `multipart/form-data`  
**Form Field:** `avatar` (file, max 2MB, chỉ JPG/PNG/WEBP)

**Response (200):**

```json
{
  "data": {
    "avatarUrl": "public/assets/images/avatars/avatar_5_1711540800.jpg"
  }
}
```

---

## PHẦN 5: CẤU TRÚC DATABASE CHUNG

### 5.1 Sơ đồ bảng chính

```
┌─────────────┐     ┌─────────────┐     ┌──────────────┐
│  NguoiDung  │     │  BenhNhan   │     │   VaiTro     │
│─────────────│     │─────────────│     │──────────────│
│ MaNguoiDung │◄────│ SoDienThoai │     │ MaVaiTro     │
│ HoTen       │     │ MaBenhNhan  │     │ TenVaiTro    │
│ MaVaiTro    │────►│ HoTen       │     │ 1=Admin      │
│ SoDienThoai │     │ NgaySinh    │     │ 2=Bác Sĩ     │
│ Email       │     │ GioiTinh    │     │ 3=Lễ Tân     │
│ MatKhau     │     │ DiaChi      │     │ 4=Bệnh Nhân  │
│ TrangThaiTK │     │ Email       │     └──────────────┘
└─────────────┘     │ IsDeleted   │
                    └──────┬──────┘
                           │
            ┌──────────────┼──────────────┐
            │              │              │
   ┌────────▼───────┐ ┌───▼──────┐ ┌─────▼──────────┐
   │   LichHen      │ │ThanhVien │ │   DanhGia      │
   │────────────────│ │Info      │ │────────────────│
   │ MaLichHen      │ │──────────│ │ MaDanhGia      │
   │ MaBenhNhan     │ │MaThanhVien│ │ MaPhieuKham    │
   │ NgayHen        │ │MaBenhNhan│ │ MaBenhNhan     │
   │ ThoiGianHen    │ │DiemThuong│ │ DiemDanh (1-5) │
   │ GhiChu         │ │MaHangTV  │ │ NhanXet        │
   │ TrangThai(0-3) │ │TongChiTieu│ │ NgayDanhGia    │
   └────────┬───────┘ │TyLeHaiLong│ └────────────────┘
            │         └──────────┘
   ┌────────▼───────┐           ┌──────────────────┐
   │  PhieuKham     │           │  HangThanhVien   │
   │────────────────│           │──────────────────│
   │ MaPhieuKham    │           │ MaHangThanhVien  │
   │ MaBenhNhan     │           │ TenHang          │
   │ MaLichHen      │           │ DiemToiThieu     │
   │ MaBacSi        │           │ MucGiamGia       │
   │ NgayKham       │           │ MoTaUuDai        │
   │ TrieuChung     │           │──────────────────│
   │ ChanDoan       │           │ 1: Đồng (0đ, 0%)│
   │ HuongDieuTri   │           │ 2: Bạc (300đ,5%)│
   │ TrangThai(0-2) │           │ 3: Vàng(1000đ,10%)|
   └───┬────────┬───┘           │ 4: KC(5000đ,15%)│
       │        │               └──────────────────┘
       │        │
  ┌────▼────┐ ┌─▼─────────────┐ ┌──────────────┐
  │ChiTiet  │ │ChiTietDonThuoc│ │   HoaDon     │
  │DichVu   │ │───────────────│ │──────────────│
  │─────────│ │MaPhieuKham    │ │ MaHoaDon     │
  │MaPhieuKham│ │MaThuoc       │ │ MaPhieuKham  │
  │MaDichVu │ │SoLuong        │ │ TongTienDichVu│
  │SoLuong  │ │LieuDung       │ │ TongThuoc    │
  │DonGia   │ │GhiChu         │ │ GiamGia      │
  │ThanhTien│ └───────────────┘ │ TongTien     │
  └─────────┘                   │ TienKhachTra │
                                │ TienThua     │
  ┌─────────┐ ┌───────────┐    │ PhuongThuc   │
  │ DichVu  │ │   Thuoc   │    │ NgayThanhToan│
  │─────────│ │───────────│    │ TrangThai(0-1)|
  │MaDichVu │ │MaThuoc    │    └──────────────┘
  │TenDichVu│ │TenThuoc   │
  │DonGia   │ │DonGia     │
  │MoTa     │ │DonViTinh  │
  └─────────┘ │HanSuDung  │
              └───────────┘
```

### 5.2 Quy ước chung

| Quy ước                           | Giá trị                                                  |
| --------------------------------- | -------------------------------------------------------- |
| **Soft Delete**                   | Tất cả bảng có cột `IsDeleted` (0 = active, 1 = deleted) |
| **Timestamp**                     | `NgayTao` (tự động GETDATE()), `NgaySua`                 |
| **Tiền tệ**                       | Đơn vị VNĐ, kiểu `DECIMAL(18,2)`                         |
| **Mã khóa chính**                 | Auto-increment (IDENTITY)                                |
| **Liên kết NguoiDung ↔ BenhNhan** | Qua trường `SoDienThoai`                                 |

---

### 5.3 Stored Procedures quan trọng

| Stored Procedure              | Mô tả                                  | Tham số                                     |
| ----------------------------- | -------------------------------------- | ------------------------------------------- |
| `SP_DatLichHen`               | Đặt lịch hẹn (tự tạo BenhNhan nếu mới) | @HoTen, @SoDienThoai, @ThoiGianHen, @GhiChu |
| `SP_GuiOTP_NguoiDung`         | Tạo và lưu mã OTP                      | @SoDienThoai                                |
| `SP_XacThucOTP_NguoiDung`     | Xác thực mã OTP                        | @SoDienThoai, @MaOTP                        |
| `SP_CapNhatThongTinNguoiDung` | Cập nhật hồ sơ                         | @MaNguoiDung, @HoTen, @Email                |

---

## TỔNG KẾT

### Bảng tổng hợp API Endpoints

| Nhóm       | GET    | POST   | PUT   | DELETE | Tổng   |
| ---------- | ------ | ------ | ----- | ------ | ------ |
| Auth & OTP | 1      | 12     | 0     | 0      | 13     |
| BenhNhan   | 7      | 1      | 1     | 1      | 10     |
| LichHen    | 5      | 3      | 2     | 0      | 10     |
| PhieuKham  | 5      | 1      | 2     | 0      | 8      |
| HoaDon     | 5      | 1      | 1     | 1      | 8      |
| DanhGia    | 1      | 1      | 0     | 0      | 2      |
| ThanhVien  | 5      | 1      | 2     | 1      | 9      |
| Profile    | 0      | 2      | 0     | 0      | 2      |
| **Tổng**   | **29** | **22** | **8** | **3**  | **62** |

### Checklist cho Team Windows App

- [ ] Kết nối cùng SQL Server `DERMASOFT` với connection string ở trên
- [ ] Sau khi INSERT dữ liệu (PhieuKham, HoaDon...), Website hiển thị tự động
- [ ] Implement Timer polling 15 giây để check lịch hẹn mới từ Website
- [ ] Đảm bảo `IsDeleted = 0` trong mọi query
- [ ] Dùng đúng giá trị `TrangThai` theo bảng 3.2
- [ ] Khi tạo HoaDon, tính đúng: `TongTien = TongTienDichVu + TongThuoc - GiamGia`
- [ ] Thuốc lấy giá từ bảng `Thuoc.DonGia`, `ThanhTien = SoLuong × DonGia`

---

> **Liên hệ hỗ trợ:** Nếu gặp vấn đề khi tích hợp, kiểm tra:
>
> 1. Connection string có đúng không?
> 2. SQL Server có cho phép remote connection không? (nếu khác máy)
> 3. Firewall port 1433 có mở không? (nếu khác máy)
> 4. Dữ liệu INSERT có đúng format và FK constraint không?
