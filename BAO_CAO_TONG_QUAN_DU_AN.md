# BÁO CÁO TỔNG QUAN DỰ ÁN DARMASOFT

## 1. Giới thiệu dự án

**DarmaSoft** là hệ thống quản lý phòng khám da liễu trực tuyến, bao gồm:

- **Website** dành cho khách hàng (đặt lịch, xem hồ sơ, đánh giá)
- **Trang quản trị** dành cho Admin (quản lý bệnh nhân, thành viên, đánh giá)
- **REST API** phục vụ tích hợp với ứng dụng Windows App (quản lý phiếu khám, hóa đơn, xác nhận lịch hẹn)

**Tên CSDL:** DERMASOFT  
**Nền tảng:** PHP MVC + SQL Server, triển khai trên XAMPP (Apache)

---

## 2. Các chức năng chính

### 2.1. Đặt lịch hẹn khám

- Khách vãng lai hoặc người dùng đã đăng nhập đều có thể đặt lịch
- Khách vãng lai nhập Họ tên + SĐT; nếu SĐT đã đăng ký tài khoản, hệ thống yêu cầu đăng nhập
- Người dùng đã đăng nhập sẽ được tự động điền thông tin từ session (không cho sửa)
- Tài khoản Admin (MaVaiTro = 1) không được phép đặt lịch
- Giới hạn: tối đa 5 lịch hẹn đang chờ xử lý/SĐT, không trùng ngày, đặt trong vòng 1–60 ngày tới
- Trạng thái lịch hẹn: 0 = Chờ xác nhận → 1 = Đã xác nhận → 2 = Hoàn thành → 3 = Hủy
- Lịch hẹn được tạo qua Stored Procedure `SP_DatLichHen`

### 2.2. Xác thực người dùng (OTP)

- **Đăng nhập:** Nhập SĐT → Gửi OTP qua SMS/Email → Xác thực OTP → Tạo session
- **Đăng ký:** Nhập SĐT (kiểm tra chưa tồn tại) → Gửi OTP → Xác thực → Tạo tài khoản
- **Quên mật khẩu:** Nhập SĐT → Gửi OTP → Xác thực → Đặt lại mật khẩu
- OTP gồm 6 chữ số, hiệu lực 5 phút
- SMS gửi qua dịch vụ eSMS (chính), SpeedSMS, Twilio (dự phòng)
- Email gửi qua Gmail SMTP (PHPMailer)
- Giới hạn gửi OTP: 5 lần/phút, 50 lần/giờ, 500 lần/ngày

### 2.3. Hồ sơ cá nhân (Profile)

- Xem và chỉnh sửa thông tin cá nhân (Họ tên, Email)
- Upload ảnh đại diện (JPG, PNG, WEBP — tối đa 2MB)
- Xem hạng thành viên hiện tại và lộ trình thăng hạng
- Xem danh sách lịch hẹn sắp tới (trạng thái Chờ xác nhận + Đã xác nhận)
- Hủy lịch hẹn đang ở trạng thái Chờ xác nhận (TrangThai = 0)
- Xem lịch sử khám bệnh (10 phiếu khám gần nhất)
- Xem chi tiết phiếu khám (bao gồm dịch vụ, thuốc, kết quả)
- Đánh giá dịch vụ (1–5 sao) sau khi hoàn thành phiếu khám

### 2.4. Hệ thống hạng thành viên

- Mỗi bệnh nhân được gắn vào bảng `ThanhVienInfo` khi có hoạt động
- Tích lũy điểm qua các lần khám, thanh toán
- Hạng thành viên tự động nâng cấp dựa trên tổng điểm tích lũy
- Mỗi hạng có màu sắc, tỷ lệ giảm giá, ghi chú khuyến mãi riêng
- Thanh tiến trình (progress bar) hiển thị khoảng cách đến hạng tiếp theo

### 2.5. Quản trị (Admin Panel)

- **Dashboard:** Thống kê tổng quan — tổng bệnh nhân, thành viên, lịch hẹn chờ, đánh giá trung bình, doanh thu
- **Quản lý bệnh nhân:** Xem danh sách, tìm kiếm, cập nhật thông tin
- **Quản lý thành viên:** Xem điểm tích lũy, hạng hiện tại, tỷ lệ hài lòng
- **Quản lý hạng thành viên:** CRUD hạng thành viên (tên, điểm tối thiểu, giảm giá, màu)
- **Quản lý đánh giá:** Xem đánh giá của bệnh nhân, thống kê sao, xóa đánh giá
- Chỉ tài khoản có MaVaiTro = 1 mới truy cập được

### 2.6. Tích hợp Windows App

- Windows App kết nối cùng CSDL DERMASOFT qua REST API
- **Xác nhận lịch hẹn:** `POST /api/lichhens/{id}/confirm` — Chuyển TrangThai từ 0 → 1
- **Quản lý phiếu khám:** `GET/POST /api/phieukham/*` — Ghi kết quả khám, thêm dịch vụ/thuốc
- **Quản lý hóa đơn:** `GET/PUT /api/hoadon/*` — Tạo hóa đơn, cập nhật thanh toán
- **Cập nhật trạng thái lịch hẹn:** `PUT /api/lichhens/{id}/status` — Thay đổi trạng thái 0–3

---

## 3. Luồng hoạt động chính

### 3.1. Luồng đặt lịch hẹn

```
Khách truy cập Website → Điền form đặt lịch (Tên, SĐT, Ngày, Giờ, Ghi chú)
  → API POST /api/booking/create
    → Kiểm tra: SĐT hợp lệ, không quá 5 lịch chờ, không trùng ngày
    → Gọi SP_DatLichHen (tạo BenhNhan nếu chưa có + tạo LichHen TrangThai=0)
    → Trả kết quả: MaLichHen, MaBenhNhan, ThoiGianHen
  → Hiển thị thông báo thành công
```

### 3.2. Luồng đăng nhập OTP

```
Người dùng nhập SĐT → POST /api/auth/check-phone (kiểm tra SĐT tồn tại)
  → POST /api/auth/send-otp-login (gửi OTP qua SMS/Email)
    → Tạo mã 6 số, lưu vào bảng XacThucOTP, gửi tin nhắn
  → Người dùng nhập OTP → POST /api/auth/login-with-otp
    → Xác thực OTP → Tạo session → Redirect trang chủ
```

### 3.3. Luồng khám bệnh (tích hợp Windows App)

```
Lễ tân xác nhận lịch hẹn (TrangThai: 0 → 1)
  → Bác sĩ tạo phiếu khám → Nhập kết quả khám, dịch vụ, thuốc
  → Tạo hóa đơn → Bệnh nhân thanh toán
  → Cập nhật điểm thành viên → Nâng hạng tự động
  → Bệnh nhân đăng nhập Website → Xem kết quả → Đánh giá dịch vụ
```

### 3.4. Luồng hủy lịch hẹn

```
Bệnh nhân vào Profile → Tab "Lịch hẹn khám" → Nhấn "Hủy lịch"
  → POST /api/lichhens/{id}/cancel
    → Kiểm tra: lịch đang Chờ xác nhận (TrangThai=0) + thuộc sở hữu người dùng
    → Cập nhật TrangThai = 3 (Hủy)
  → Tải lại trang
```

---

## 4. Danh sách API Endpoints

### Xác thực (Auth)

| Method | Endpoint                         | Mô tả                         |
| ------ | -------------------------------- | ----------------------------- |
| POST   | `/api/auth/check-phone`          | Kiểm tra SĐT tồn tại          |
| POST   | `/api/auth/send-otp-login`       | Gửi OTP đăng nhập             |
| POST   | `/api/auth/login-with-otp`       | Đăng nhập bằng OTP            |
| POST   | `/api/auth/register-phone`       | Đăng ký tài khoản qua SĐT     |
| POST   | `/api/auth/forgot-phone`         | Quên mật khẩu                 |
| POST   | `/api/auth/update-phone`         | Cập nhật SĐT                  |
| POST   | `/api/auth/verify-email-token`   | Xác thực email token          |
| POST   | `/api/auth/send-otp-phone-reset` | Gửi OTP đổi SĐT               |
| POST   | `/api/auth/reset-phone-with-otp` | Đổi SĐT bằng OTP              |
| POST   | `/api/auth/login`                | Đăng nhập (username/password) |
| POST   | `/api/auth/logout`               | Đăng xuất                     |
| GET    | `/api/auth/me`                   | Lấy thông tin user hiện tại   |

### Đặt lịch

| Method | Endpoint              | Mô tả            |
| ------ | --------------------- | ---------------- |
| POST   | `/api/booking/create` | Đặt lịch hẹn mới |

### Lịch hẹn

| Method | Endpoint                        | Mô tả                           |
| ------ | ------------------------------- | ------------------------------- |
| GET    | `/api/lichhens`                 | Danh sách lịch hẹn (phân trang) |
| GET    | `/api/lichhens/{id}`            | Chi tiết lịch hẹn               |
| GET    | `/api/lichhens/patient/{id}`    | Lịch hẹn theo bệnh nhân         |
| GET    | `/api/lichhens/status/{status}` | Lịch hẹn theo trạng thái        |
| GET    | `/api/lichhens/pending`         | Lịch hẹn chờ xác nhận           |
| POST   | `/api/lichhens`                 | Tạo lịch hẹn                    |
| PUT    | `/api/lichhens/{id}`            | Cập nhật lịch hẹn               |
| PUT    | `/api/lichhens/{id}/status`     | Cập nhật trạng thái             |
| POST   | `/api/lichhens/{id}/confirm`    | Xác nhận lịch hẹn               |
| POST   | `/api/lichhens/{id}/cancel`     | Hủy lịch hẹn                    |

### Bệnh nhân

| Method | Endpoint                      | Mô tả               |
| ------ | ----------------------------- | ------------------- |
| GET    | `/api/benhnhan`               | Danh sách bệnh nhân |
| GET    | `/api/benhnhan/{id}`          | Chi tiết bệnh nhân  |
| GET    | `/api/benhnhan/search?q=`     | Tìm kiếm bệnh nhân  |
| GET    | `/api/benhnhan/phone/{phone}` | Tìm theo SĐT        |
| POST   | `/api/benhnhan`               | Thêm bệnh nhân      |
| PUT    | `/api/benhnhan/{id}`          | Cập nhật bệnh nhân  |

### Phiếu khám

| Method | Endpoint                          | Mô tả                     |
| ------ | --------------------------------- | ------------------------- |
| GET    | `/api/phieukham`                  | Danh sách phiếu khám      |
| GET    | `/api/phieukham/{id}`             | Chi tiết phiếu khám       |
| GET    | `/api/phieukham/patient/{id}`     | Phiếu khám theo bệnh nhân |
| GET    | `/api/phieukham/appointment/{id}` | Phiếu khám theo lịch hẹn  |
| POST   | `/api/phieukham`                  | Tạo phiếu khám            |
| PUT    | `/api/phieukham/{id}`             | Cập nhật phiếu khám       |

### Hóa đơn

| Method | Endpoint                   | Mô tả                   |
| ------ | -------------------------- | ----------------------- |
| GET    | `/api/hoadon`              | Danh sách hóa đơn       |
| GET    | `/api/hoadon/{id}`         | Chi tiết hóa đơn        |
| GET    | `/api/hoadon/patient/{id}` | Hóa đơn theo bệnh nhân  |
| GET    | `/api/hoadon/unpaid`       | Hóa đơn chưa thanh toán |
| GET    | `/api/hoadon/paid`         | Hóa đơn đã thanh toán   |
| PUT    | `/api/hoadon/{id}/payment` | Cập nhật thanh toán     |

### Đánh giá

| Method | Endpoint                           | Mô tả                     |
| ------ | ---------------------------------- | ------------------------- |
| POST   | `/api/danhgia`                     | Gửi đánh giá              |
| GET    | `/api/danhgia/check/{maPhieuKham}` | Kiểm tra đã đánh giá chưa |

### Hồ sơ cá nhân

| Method | Endpoint                     | Mô tả               |
| ------ | ---------------------------- | ------------------- |
| POST   | `/api/profile/update`        | Cập nhật thông tin  |
| POST   | `/api/profile/upload-avatar` | Upload ảnh đại diện |

### Thành viên

| Method | Endpoint                      | Mô tả                |
| ------ | ----------------------------- | -------------------- |
| GET    | `/api/thanh-vien/{id}`        | Thông tin thành viên |
| GET    | `/api/thanh-vien/{id}/points` | Điểm tích lũy        |
| PUT    | `/api/thanh-vien/{id}/points` | Cập nhật điểm        |

### Admin

| Method | Endpoint                          | Mô tả                |
| ------ | --------------------------------- | -------------------- |
| GET    | `/api/admin/stats`                | Thống kê tổng quan   |
| GET    | `/api/admin/benh-nhan`            | Danh sách bệnh nhân  |
| GET    | `/api/admin/benh-nhan/{id}`       | Chi tiết bệnh nhân   |
| PUT    | `/api/admin/benh-nhan/{id}`       | Cập nhật bệnh nhân   |
| POST   | `/api/admin/toggle-status`        | Bật/tắt trạng thái   |
| POST   | `/api/admin/reset-password`       | Reset mật khẩu       |
| GET    | `/api/admin/thanh-vien`           | Danh sách thành viên |
| PUT    | `/api/admin/thanh-vien/{id}`      | Cập nhật thành viên  |
| GET    | `/api/admin/hang-thanh-vien`      | Danh sách hạng       |
| POST   | `/api/admin/hang-thanh-vien`      | Tạo hạng mới         |
| PUT    | `/api/admin/hang-thanh-vien/{id}` | Cập nhật hạng        |
| DELETE | `/api/admin/hang-thanh-vien/{id}` | Xóa hạng             |
| GET    | `/api/admin/danh-gia`             | Danh sách đánh giá   |
| DELETE | `/api/admin/danh-gia/{id}`        | Xóa đánh giá         |
| GET    | `/api/admin/danh-gia-stats`       | Thống kê đánh giá    |

---

## 5. Cơ sở dữ liệu

### Các bảng chính

| Bảng                | Mô tả                                    | Khóa chính  |
| ------------------- | ---------------------------------------- | ----------- |
| `NguoiDung`         | Người dùng hệ thống (nhân viên, admin)   | MaNguoiDung |
| `VaiTro`            | Vai trò/quyền hạn (1=Admin, 4=Bệnh nhân) | MaVaiTro    |
| `BenhNhan`          | Thông tin bệnh nhân                      | MaBenhNhan  |
| `LichHen`           | Lịch hẹn khám (TrangThai: 0–3)           | MaLichHen   |
| `PhieuKham`         | Phiếu khám bệnh                          | MaPhieuKham |
| `DichVu`            | Danh mục dịch vụ y tế                    | MaDichVu    |
| `HoaDon`            | Hóa đơn thanh toán                       | MaHoaDon    |
| `DanhGia`           | Đánh giá của bệnh nhân (1–5 sao)         | MaDanhGia   |
| `ThanhVienInfo`     | Thông tin thành viên (điểm, hạng)        | MaThanhVien |
| `HangThanhVien`     | Định nghĩa các hạng thành viên           | MaHang      |
| `XacThucOTP`        | Lưu mã OTP xác thực                      | MaXacThuc   |
| `ThongTinPhongKham` | Thông tin phòng khám                     | ID          |

### Ràng buộc quan trọng

- `CHK_TrangThaiLich`: LichHen.TrangThai BETWEEN 0 AND 3
- `CHK_TrangThaiPhieu`: PhieuKham.TrangThai (0=Chờ, 1=Hoàn thành, 2=Hủy)
- Soft delete: Các bảng sử dụng cột `IsDeleted` thay vì xóa vật lý

### Quan hệ giữa các bảng

```
NguoiDung ──(MaVaiTro)──> VaiTro
BenhNhan ──(1:N)──> LichHen
BenhNhan ──(1:N)──> PhieuKham
BenhNhan ──(1:1)──> ThanhVienInfo ──(N:1)──> HangThanhVien
LichHen ──(1:1)──> PhieuKham
PhieuKham ──(1:1)──> HoaDon
PhieuKham ──(1:N)──> DanhGia
NguoiDung ──(1:N)──> LichHen (MaNguoiDung — bác sĩ phụ trách)
```

---

## 6. Bảo mật

| Cơ chế                | Chi tiết                                                     |
| --------------------- | ------------------------------------------------------------ |
| Session timeout       | 1 giờ tự động đăng xuất                                      |
| Mật khẩu              | Hỗ trợ bcrypt (khuyến nghị), md5 (legacy), plain text (test) |
| Chống giả mạo SĐT     | User đã đăng nhập → ép dùng SĐT từ session                   |
| Phân quyền            | Admin (MaVaiTro=1) mới vào trang quản trị                    |
| Sở hữu dữ liệu        | Hủy lịch hẹn chỉ được nếu lịch thuộc bệnh nhân của user      |
| Rate limiting OTP     | 5/phút, 50/giờ, 500/ngày                                     |
| Rate limiting booking | Tối đa 5 lịch hẹn chờ/SĐT                                    |
| XSS Protection        | `htmlspecialchars()` với `ENT_QUOTES` trên mọi output        |
| Upload validation     | Chỉ JPG/PNG/WEBP, tối đa 2MB                                 |
| SQL Injection         | Sử dụng PDO Prepared Statements                              |
| Soft Delete           | Không xóa vật lý dữ liệu, đánh dấu IsDeleted                 |

---

## 7. Tổng kết

DarmaSoft là hệ thống quản lý phòng khám da liễu hoàn chỉnh với kiến trúc MVC rõ ràng, REST API đầy đủ cho tích hợp đa nền tảng, hệ thống xác thực OTP an toàn, và cơ chế phân quyền chặt chẽ. Dự án phục vụ 3 nhóm người dùng chính: **Khách hàng** (đặt lịch, xem kết quả), **Bác sĩ/Lễ tân** (quản lý khám bệnh qua Windows App), và **Quản trị viên** (thống kê, quản lý hệ thống).
