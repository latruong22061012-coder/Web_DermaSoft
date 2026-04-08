# BÁO CÁO KỸ THUẬT DỰ ÁN DARMASOFT

## 1. Cấu trúc dự án

### 1.1. Tổng quan kiến trúc

Dự án sử dụng mô hình **MVC (Model-View-Controller)** tùy chỉnh, không dùng framework bên thứ ba. Cấu trúc được tổ chức như sau:

```
DarmaSoft/
│
├── index.php                          # Entry point + Router chính
├── .htaccess                          # URL Rewriting (Apache mod_rewrite)
├── HUONG_DAN_TICH_HOP_VA_API.md       # Tài liệu tích hợp API
│
├── app/                               # Mã nguồn ứng dụng (Backend)
│   ├── Config/                        # Cấu hình
│   │   ├── config.php                 #   Kết nối CSDL SQL Server
│   │   ├── email_config.php           #   Cấu hình SMTP Gmail
│   │   ├── sms_config.php             #   Cấu hình SMS Gateway (eSMS/SpeedSMS/Twilio)
│   │   └── PHPMailer-master/          #   Thư viện gửi email
│   │
│   ├── Core/                          # Lõi framework
│   │   ├── Auth.php                   #   Xác thực & quản lý session
│   │   ├── Controller.php             #   Base Controller (abstract class)
│   │   ├── Database.php               #   PDO Wrapper cho SQL Server
│   │   └── Model.php                  #   Base Model (Generic CRUD)
│   │
│   ├── Controllers/                   # Controllers xử lý logic
│   │   ├── HomeController.php         #   Trang chủ
│   │   ├── AuthController.php         #   Đăng nhập/Đăng ký/Quên mật khẩu
│   │   ├── ProfileController.php      #   Hồ sơ cá nhân
│   │   ├── AdminController.php        #   Trang quản trị
│   │   ├── ApiController.php          #   Base API Controller
│   │   └── Api/                       #   API Controllers
│   │       ├── AuthController.php     #     Xác thực API (login/logout/me)
│   │       ├── OtpEmailController.php #     OTP qua SMS/Email
│   │       ├── BookingController.php  #     Đặt lịch hẹn
│   │       ├── LichHenController.php  #     Quản lý lịch hẹn
│   │       ├── BenhNhanController.php #     Quản lý bệnh nhân
│   │       ├── PhieuKhamController.php#     Quản lý phiếu khám
│   │       ├── HoaDonController.php   #     Quản lý hóa đơn
│   │       ├── DanhGiaController.php  #     Đánh giá dịch vụ
│   │       ├── ProfileApiController.php#    Cập nhật hồ sơ
│   │       ├── ThanhVienInfoController.php# Thông tin thành viên
│   │       └── AdminApiController.php #     API quản trị
│   │
│   ├── Models/                        # Models (ORM đơn giản)
│   │   ├── User.php                   #   Người dùng (NguoiDung)
│   │   ├── BenhNhan.php               #   Bệnh nhân
│   │   ├── LichHen.php                #   Lịch hẹn
│   │   ├── PhieuKham.php              #   Phiếu khám
│   │   ├── DichVu.php                 #   Dịch vụ y tế
│   │   ├── HoaDon.php                 #   Hóa đơn
│   │   ├── DanhGia.php                #   Đánh giá
│   │   ├── ThanhVienInfo.php          #   Thông tin thành viên
│   │   ├── HangThanhVien.php          #   Hạng thành viên
│   │   ├── XacThucOTP.php             #   Xác thực OTP
│   │   ├── ThongTinPhongKham.php      #   Thông tin phòng khám
│   │   └── VaiTro.php                 #   Vai trò/Quyền
│   │
│   ├── Services/                      # Business Logic Services
│   │   ├── EmailService.php           #   Gửi email (PHPMailer)
│   │   └── SmsService.php             #   Gửi SMS (eSMS API)
│   │
│   └── Views/                         # Giao diện
│       ├── home.php                   #   Trang chủ
│       ├── login.php                  #   Đăng nhập
│       ├── register.php               #   Đăng ký
│       ├── forgot-password.php        #   Quên mật khẩu
│       ├── profile.php                #   Hồ sơ cá nhân
│       └── admin/                     #   Trang quản trị
│           ├── layout-header.php      #     Header/Sidebar chung
│           ├── layout-footer.php      #     Footer/Scripts chung
│           ├── dashboard.php          #     Dashboard
│           ├── benh-nhan.php          #     Quản lý bệnh nhân
│           ├── thanh-vien.php         #     Quản lý thành viên
│           ├── hang-thanh-vien.php    #     Quản lý hạng
│           └── danh-gia.php           #     Quản lý đánh giá
│
└── public/                            # Tài nguyên tĩnh (Frontend)
    └── assets/
        ├── css/
        │   ├── style.css              #   Stylesheet chính
        │   ├── auth.css               #   Style trang xác thực
        │   ├── profile.css            #   Style trang hồ sơ
        │   └── admin.css              #   Style trang quản trị
        ├── js/
        │   ├── script.js              #   Script chính (navbar, booking form, AOS)
        │   ├── profile.js             #   Script hồ sơ (avatar, profile form, tabs)
        │   └── otp-api-handler.js     #   Xử lý OTP API (check phone, send/verify OTP)
        ├── images/                    #   Hình ảnh tĩnh
        │   └── avatars/               #   Ảnh đại diện upload bởi user
        └── vendor/                    #   Thư viện bên thứ ba
            ├── bootstrap/             #     Bootstrap 5
            ├── bootstrap-icons/       #     Bootstrap Icons
            └── aos/                   #     Animate On Scroll
```

### 1.2. Mô hình MVC chi tiết

| Thành phần     | Vai trò                                                                                 | Vị trí             |
| -------------- | --------------------------------------------------------------------------------------- | ------------------ |
| **Model**      | Đại diện cho dữ liệu và logic nghiệp vụ với CSDL. Kế thừa từ `Model.php` (Generic CRUD) | `app/Models/`      |
| **View**       | Giao diện HTML/PHP render phía server, sử dụng biến được truyền từ Controller           | `app/Views/`       |
| **Controller** | Nhận request, gọi Model, truyền dữ liệu cho View hoặc trả JSON (API)                    | `app/Controllers/` |
| **Router**     | Xử lý trong `index.php`, phân biệt Web routes (`?route=`) và API routes (`/api/`)       | `index.php`        |

---

## 2. Ngôn ngữ sử dụng

| Ngôn ngữ              | Phiên bản  | Phạm vi sử dụng                                        | Tỷ lệ ước tính |
| --------------------- | ---------- | ------------------------------------------------------ | -------------- |
| **PHP**               | 8.x        | Backend: Controllers, Models, Services, Views, Routing | 60%            |
| **JavaScript** (ES5+) | Vanilla JS | Frontend: Xử lý form, AJAX/Fetch API, DOM manipulation | 15%            |
| **HTML5**             | —          | Cấu trúc giao diện, Forms, Semantic markup             | 10%            |
| **CSS3**              | —          | Styling, Responsive design, Animations, CSS Variables  | 10%            |
| **SQL** (T-SQL)       | SQL Server | Stored Procedures, Queries, Constraints, Triggers      | 5%             |

### Ghi chú về ngôn ngữ

- **PHP**: Sử dụng `declare(strict_types=1)`, namespace, type hints, union types (`array|null`)
- **JavaScript**: Không dùng framework (React/Vue), thuần Vanilla JS với Fetch API cho AJAX
- **CSS**: Sử dụng CSS Custom Properties (biến), Flexbox, Grid
- **SQL**: T-SQL syntax cho SQL Server (GETDATE(), TOP, SCOPE_IDENTITY(), v.v.)

---

## 3. Công nghệ sử dụng

### 3.1. Backend

| Công nghệ               | Phiên bản | Mục đích                                                   |
| ----------------------- | --------- | ---------------------------------------------------------- |
| **PHP**                 | 8.x       | Ngôn ngữ lập trình server-side                             |
| **SQL Server**          | (MSSQL)   | Cơ sở dữ liệu quan hệ                                      |
| **PDO** (sqlsrv driver) | —         | Kết nối CSDL với Prepared Statements (phòng SQL Injection) |
| **PHPMailer**           | 6.x       | Gửi email qua SMTP (Gmail)                                 |
| **Apache** (XAMPP)      | —         | Web server với mod_rewrite                                 |

### 3.2. Frontend

| Công nghệ                   | Phiên bản | Mục đích                                       |
| --------------------------- | --------- | ---------------------------------------------- |
| **Bootstrap**               | 5.x       | CSS Framework responsive                       |
| **Bootstrap Icons**         | —         | Bộ icon SVG/Font                               |
| **AOS** (Animate On Scroll) | 2.x       | Hiệu ứng cuộn trang                            |
| **Fetch API**               | —         | Gọi REST API từ browser (thay thế jQuery AJAX) |
| **Google Fonts**            | —         | Font chữ: Montserrat, Playfair Display         |

### 3.3. Dịch vụ bên ngoài

| Dịch vụ            | Mục đích                                | Cấu hình                                |
| ------------------ | --------------------------------------- | --------------------------------------- |
| **eSMS.vn**        | Gửi SMS OTP (chính)                     | API Key + Secret trong `sms_config.php` |
| **SpeedSMS**       | Gửi SMS OTP (dự phòng)                  | API Token                               |
| **Twilio**         | Gửi SMS OTP (dự phòng)                  | Account SID + Auth Token                |
| **Gmail SMTP**     | Gửi email OTP, xác nhận, reset mật khẩu | smtp.gmail.com:587, TLS                 |
| **ui-avatars.com** | Tạo avatar mặc định từ tên người dùng   | API miễn phí                            |

### 3.4. Công cụ phát triển

| Công cụ                          | Mục đích                                   |
| -------------------------------- | ------------------------------------------ |
| **XAMPP**                        | Môi trường phát triển local (Apache + PHP) |
| **VS Code**                      | IDE chính                                  |
| **SQL Server Management Studio** | Quản lý CSDL                               |

---

## 4. Thuật toán và Logic nghiệp vụ

### 4.1. Hệ thống định tuyến (Routing)

```
Thuật toán: Pattern Matching + Resource Mapping

index.php nhận REQUEST_URI:
1. Loại bỏ prefix "/DarmaSoft" nếu có
2. Nếu bắt đầu bằng "/api/" → handleApiRoute()
   a. Tách URI thành: /api/{resource}/{method}/{id}
   b. Tra bảng ánh xạ $routes[] → tìm Controller class
   c. Xác định HTTP method (GET/POST/PUT/DELETE)
   d. Routing theo convention:
      - GET /resource        → index()
      - GET /resource/{id}   → show()
      - POST /resource       → create()
      - PUT /resource/{id}   → update()
      - DELETE /resource/{id} → delete()
   e. Các route đặc biệt: /confirm, /cancel, /status, /patient/{id}
3. Nếu không phải API → đọc ?route= → switch-case Web routes
```

### 4.2. Xác thực OTP

```
Thuật toán: Time-based One-Time Password (đơn giản hóa)

Tạo OTP:
1. Sinh chuỗi 6 chữ số ngẫu nhiên: random_int(100000, 999999)
2. Lưu vào bảng XacThucOTP: {SĐT, MãOTP, ThoiGianHetHan = NOW + 5 phút}
3. Xóa OTP cũ chưa dùng của cùng SĐT
4. Gửi OTP qua SMS hoặc Email

Xác thực OTP:
1. Nhận SĐT + mã OTP từ request
2. Truy vấn: SELECT * FROM XacThucOTP WHERE SĐT = ? AND MaOTP = ? AND ThoiGianHetHan > NOW
3. Nếu tìm thấy → Xác thực thành công → Xóa OTP → Tạo session
4. Nếu không → Tăng SoLanSai (tối đa 3 lần → khóa OTP đó)

Rate Limiting:
- Đếm số lần gửi OTP theo SĐT trong khoảng thời gian
- Giới hạn: 5/phút, 50/giờ, 500/ngày
```

### 4.3. Hệ thống hạng thành viên

```
Thuật toán: Point-based Tier Mapping

Tính hạng hiện tại:
1. Truy vấn TongDiem từ ThanhVienInfo theo MaBenhNhan
2. Truy vấn tất cả HangThanhVien sắp xếp theo DiemToiThieu DESC
3. Duyệt từ hạng cao nhất → thấp nhất:
   - Nếu TongDiem >= DiemToiThieu → Đây là hạng hiện tại
4. Tính hạng tiếp theo:
   - Lấy hạng liền trên hạng hiện tại
   - DiemConLai = DiemToiThieu(hạng sau) - TongDiem hiện tại
   - ProgressPercent = (TongDiem - DiemToiThieu(hạng hiện tại)) /
                        (DiemToiThieu(hạng sau) - DiemToiThieu(hạng hiện tại)) × 100

Hiển thị lộ trình (Profile):
- Vẽ các node: [Hạng 1] → [Hạng 2] → ... → [Hạng N]
- Đánh dấu: done (đã qua), current (đang ở), inactive (chưa đạt)
- Progress bar từ hạng hiện tại → hạng tiếp theo
```

### 4.4. Đặt lịch hẹn (Booking Validation)

```
Thuật toán: Multi-layer Validation

Client-side (script.js):
1. Validate form: Tên ≥ 3 ký tự, SĐT regex, Ngày trong 1-60 ngày tới
2. Kết hợp bookingDate + bookingTime → "YYYY-MM-DD HH:MM"

Server-side (BookingController.php):
1. Chặn Admin đặt lịch (MaVaiTro = 1 → 403)
2. User đăng nhập → ép dùng SĐT/Tên từ session
3. Khách vãng lai → kiểm tra SĐT có thuộc tài khoản đăng ký không
4. Validate SĐT regex Việt Nam: /^(0)(3[2-9]|5[6-9]|7[06-9]|8[0-9]|9[0-9])[0-9]{7}$/
5. Validate thời gian: Phải trong tương lai, không quá 60 ngày
6. Rate limit: Đếm lịch hẹn TrangThai IN (0,1) của SĐT → tối đa 5
7. Kiểm tra trùng: Không cho đặt 2 lịch cùng ngày/SĐT
8. Gọi SP_DatLichHen (tạo/tìm BenhNhan + tạo LichHen)

Stored Procedure SP_DatLichHen:
1. Tìm BenhNhan theo SĐT (không ghi đè HoTen nếu đã tồn tại)
2. Nếu chưa có → INSERT BenhNhan mới
3. Kiểm tra trùng lịch cùng ngày (TrangThai IN 0,1) → RAISERROR nếu trùng
4. INSERT LichHen với TrangThai = 0 (Chờ xác nhận)
5. Trả về MaLichHen, MaBenhNhan
```

### 4.5. Xác thực mật khẩu (Multi-format)

```
Thuật toán: Cascading Password Verification

User::authenticate($username, $password):
1. Tìm user theo TenDangNhap
2. Kiểm tra tài khoản active (TrangThai = 1, IsDeleted = 0)
3. Thử xác thực theo thứ tự:
   a. bcrypt: password_verify($password, $hash) → Khuyến nghị
   b. MD5: md5($password) === $hash → Legacy
   c. Plain text: $password === $hash → Testing only
4. Nếu match → Trả về thông tin user (loại bỏ MatKhau)
5. Nếu không → Trả về false
```

### 4.6. Phân quyền truy cập (Authorization)

```
Thuật toán: Role-based Access Control (RBAC) đơn giản

Kiểm tra quyền:
1. Auth::isAuthenticated() → Kiểm tra session tồn tại + chưa hết hạn
2. Auth::hasRole($roleId) → So sánh MaVaiTro trong session với roleId
3. Auth::getCurrentUser() → Trả về mảng thông tin user từ session

Phân quyền Controller:
- AdminController::requireAdmin() → hasRole(1) || redirect
- ProfileController::index() → hasRole(1) → redirect admin/dashboard
- ApiController::requireAuth() → isAuthenticated() || 401 JSON

Phân quyền dữ liệu (Data-level):
- Hủy lịch hẹn: Kiểm tra MaBenhNhan của lịch hẹn thuộc user hiện tại
  (Tìm BenhNhan theo SĐT user → so sánh MaBenhNhan)
```

### 4.7. Đánh giá dịch vụ & Cập nhật tỷ lệ hài lòng

```
Thuật toán: Rolling Average Calculation

Khi bệnh nhân gửi đánh giá (DanhGiaController::create):
1. Kiểm tra phiếu khám đã hoàn thành (TrangThai = 1)
2. Kiểm tra chưa đánh giá phiếu khám này
3. Lưu đánh giá: {MaPhieuKham, MaBenhNhan, SoSao(1-5), BinhLuan}
4. Cập nhật TyLeHaiLong trong ThanhVienInfo:
   - Truy vấn tất cả đánh giá của bệnh nhân
   - TyLeHaiLong = AVG(SoSao) / 5 × 100 (%)
   - UPDATE ThanhVienInfo SET TyLeHaiLong = ?
```

### 4.8. Upload ảnh đại diện

```
Thuật toán: Secure File Upload

1. Validate file: Chỉ JPEG/PNG/WEBP, tối đa 2MB
2. Tạo tên file duy nhất: "avatar_{MaNguoiDung}_{timestamp}.{ext}"
3. Di chuyển file vào thư mục: public/assets/images/avatars/
4. Xóa ảnh cũ (nếu có) để tiết kiệm dung lượng
5. Cập nhật đường dẫn trong CSDL (NguoiDung.AnhDaiDien)
6. Trả về URL mới cho client cập nhật giao diện
```

### 4.9. Auto-detection Base Path (Frontend)

```
Thuật toán: Dynamic API Base Path Detection

otp-api-handler.js:
1. Lấy window.location.pathname
2. Kiểm tra có chứa "/DarmaSoft" không
3. Nếu có → API_BASE = "/DarmaSoft"
4. Nếu không → API_BASE = "" (root domain)
5. Tất cả fetch() call sử dụng: API_BASE + "/api/..."
```

---

## 5. Design Patterns sử dụng

| Pattern                      | Áp dụng tại                           | Mô tả                                              |
| ---------------------------- | ------------------------------------- | -------------------------------------------------- |
| **MVC**                      | Toàn bộ dự án                         | Tách biệt Model, View, Controller                  |
| **Active Record** (đơn giản) | `app/Core/Model.php`                  | Mỗi Model đại diện 1 bảng, có sẵn CRUD methods     |
| **Front Controller**         | `index.php`                           | Một entry point duy nhất xử lý mọi request         |
| **Template Method**          | `Controller.php`, `ApiController.php` | Base class định nghĩa cấu trúc, lớp con override   |
| **Registry**                 | `Auth.php` (Session)                  | Lưu trữ trạng thái user trong `$_SESSION`          |
| **Service Layer**            | `EmailService.php`, `SmsService.php`  | Tách logic nghiệp vụ phức tạp ra khỏi Controller   |
| **Strategy**                 | `SmsService.php`                      | Hỗ trợ nhiều SMS provider (eSMS, SpeedSMS, Twilio) |
| **Cascading Fallback**       | `User::authenticate()`                | Thử nhiều phương thức hash: bcrypt → md5 → plain   |

---

## 6. Tổng kết kỹ thuật

| Tiêu chí       | Chi tiết                                                    |
| -------------- | ----------------------------------------------------------- |
| Kiến trúc      | MVC tùy chỉnh, không framework                              |
| Ngôn ngữ chính | PHP 8.x + Vanilla JavaScript                                |
| CSDL           | SQL Server (DERMASOFT) với PDO sqlsrv driver                |
| API            | RESTful JSON API (~50+ endpoints)                           |
| Xác thực       | OTP qua SMS/Email + Session-based                           |
| Frontend       | Bootstrap 5 + AOS animations                                |
| Email          | PHPMailer + Gmail SMTP                                      |
| SMS            | eSMS.vn (chính) + SpeedSMS/Twilio (dự phòng)                |
| Bảo mật        | PDO Prepared Statements, XSS filtering, RBAC, Rate limiting |
| Triển khai     | XAMPP (Apache + PHP + SQL Server)                           |
