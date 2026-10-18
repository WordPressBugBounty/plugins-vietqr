=== VietQR ===
Contributors: diepmagik, locbt, hungndcasso
Tags: vietqr, woocommerce, payment gateway, cong thanh toan, deeplink
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 4.0.6
Version: 4.0.6
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Bấm 1 lần mở app ngân hàng, điền sẵn số tiền và nội dung chuyển khoản (VietQR Deeplink). Kèm mã QR cho 48 ngân hàng & ví.

== Description ==

= VietQR, Casso Flow hay payOS? =

Casso phát triển 3 plugin WooCommerce cho thanh toán chuyển khoản tại Việt Nam. Plugin sau có đầy đủ tính năng của plugin trước:

**VietQR** *(plugin này)* · Miễn phí

* ✅ Hiển thị mã VietQR trên trang thanh toán
* ✅ Nút mở app ngân hàng (deeplink), điền sẵn thông tin
* ❌ Tự động xác nhận đơn hàng khi nhận được tiền
* ❌ Mã QR riêng cho từng đơn, đối soát chính xác 100%

**Casso Flow** · Có phí xác nhận thanh toán · [Cài Casso Flow](https://wordpress.org/plugins/casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang/)

* ✅ Toàn bộ tính năng của VietQR
* ✅ Tự động xác nhận đơn hàng khi nhận được tiền
* ❌ Mã QR riêng cho từng đơn, đối soát chính xác 100%

**⭐ payOS** *(khuyên dùng)* · **Gói miễn phí trọn đời**, có các gói trả phí · **[Cài payOS →](https://wordpress.org/plugins/payos/)**

* ✅ Toàn bộ tính năng của VietQR và Casso Flow
* ✅ Tự động xác nhận đơn hàng khi nhận được tiền
* ✅ **Mã QR riêng cho từng đơn, đối soát chính xác 100%**

**Mệt vì phải mở app ngân hàng kiểm tra rồi xác nhận từng đơn bằng tay?** [payOS](https://wordpress.org/plugins/payos/) tạo mã VietQR riêng cho mỗi đơn hàng và tự động chuyển đơn sang "đã thanh toán" khi tiền về, đối soát chính xác 100%. Bắt đầu với gói miễn phí trọn đời.

= VietQR: thanh toán chỉ với 1 chạm =

**Bấm 1 lần, app ngân hàng mở ra với số tài khoản, số tiền và nội dung chuyển khoản đã điền sẵn.**

VietQR đưa **VietQR Deeplink** vào WooCommerce. Trên điện thoại, khách chỉ cần bấm vào ngân hàng của mình ở trang thanh toán, app ngân hàng mở ra với giao dịch đã điền sẵn: MB Bank, VietinBank iPay, BIDV SmartBanking, ACB ONE, OCB OMNI... Khách kiểm tra lại và xác nhận. Không phải gõ số tài khoản, không quên nội dung chuyển khoản, không phải chuyển qua lại giữa ảnh chụp màn hình và app.

Trên máy tính, hoặc với app ngân hàng khác, khách chỉ cần quét mã VietQR: chuẩn mã QR ngân hàng của NAPAS, được **48 ngân hàng và ví điện tử tại Việt Nam** hỗ trợ, gồm Vietcombank, VietinBank, BIDV, Agribank, Techcombank, MB, ACB, VPBank, TPBank, MoMo, ZaloPay...

= Vì sao chủ shop chọn VietQR =

* **Chốt được nhiều đơn hơn** – thanh toán chỉ cần 1 chạm hoặc 1 lần quét, thay vì chép tay 4 thông tin.
* **Đối soát dễ dàng** – mỗi giao dịch có nội dung gồm tiền tố của bạn và mã đơn hàng (ví dụ `SHOP1024`).
* **Ít tin nhắn hỏi han** – số tiền và nội dung đã điền sẵn, khách không chuyển sai số tiền hay quên nội dung.
* **Miễn phí** – không mất phí, không cần đăng ký tài khoản; tiền chuyển thẳng vào tài khoản ngân hàng của bạn.

= Cách hoạt động =

1. Nhập ngân hàng và số tài khoản tại WooCommerce > Cài đặt > Thanh toán > VietQR.
2. Khách chọn "Chuyển khoản ngân hàng (Quét mã QR)" khi thanh toán, đơn hàng chuyển sang trạng thái tạm giữ.
3. Ở trang đặt hàng thành công, khách bấm vào app ngân hàng (điện thoại) hoặc quét mã QR (máy tính) rồi xác nhận chuyển khoản.
4. Bạn nhận tiền với mã đơn hàng trong nội dung chuyển khoản và hoàn tất đơn.

= Tính năng =

**Trang thanh toán**

* Nút "Mở app ngân hàng" trên điện thoại, tag **Tự động điền** cho các app điền sẵn giao dịch (danh sách app Android và iOS tự cập nhật từ VietQR.io)
* Mã VietQR kèm nút "Lưu ảnh QR" (mở menu chia sẻ trên điện thoại, tải file trên máy tính)
* Nút sao chép số tài khoản, số tiền và nội dung chuyển khoản
* Giao diện ưu tiên điện thoại, đồng bộ với WordPress và màu nút của theme
* Khách xem lại thông tin thanh toán tại Tài khoản > Xem đơn hàng khi đơn chưa thanh toán

**Trang thanh toán (checkout)**

* Hỗ trợ cả checkout cổ điển và WooCommerce Checkout Block
* Hiển thị vài ngân hàng phổ biến kèm ô "+N ngân hàng & ví khác" thay vì cả trăm logo
* Chỉ hiển thị khi đơn vị tiền tệ của cửa hàng là VND

**Email**

* Mã QR và thông tin chuyển khoản trong email đơn hàng, cả dạng HTML và văn bản thuần
* Đã kiểm tra tương thích với các ứng dụng email phổ biến

**Không xung đột với theme, plugin khác**

* CSS và JavaScript được cô lập (class có tiền tố riêng, không dùng biến toàn cục)
* Vẫn hoạt động khi VietQR.io không phản hồi: dùng danh sách ngân hàng đã lưu hoặc có sẵn trong plugin
* Tương thích WooCommerce HPOS (High-Performance Order Storage) và multisite
* Hỗ trợ dịch, có sẵn tiếng Việt

**Dành cho lập trình viên**

* Ghi đè template `shared/templates/bank-details.php`, `shared/templates/emails/bank-details.php`, `shared/templates/plain/bank-details.php` tại `yourtheme/vietqr/`
* Filter: `vietqr_featured_bank_bins`, `vietqr_deeplink_apps`, `vietqr_deeplink_visible_apps`, `vietqr_supported_currencies`, `woocommerce_vietqr_account_fields`
* Chỉnh màu qua các biến CSS `--vietqr-*`

Mã QR và deeplink được cung cấp bởi [VietQR.IO](https://www.vietqr.io).

== External services ==

Plugin kết nối tới các dịch vụ sau của VietQR.io (https://www.vietqr.io):

* `api.vietqr.io` – tải danh sách ngân hàng (tối đa 1 lần/giờ). Dữ liệu gửi đi: địa chỉ website.
* `img.vietqr.io` – tạo ảnh mã QR hiển thị ở trang đặt hàng thành công và trong email. Dữ liệu gửi đi: mã ngân hàng, số tài khoản, tên tài khoản, số tiền và nội dung chuyển khoản của đơn hàng.
* `api.vietqr.io/v2/android-app-deeplinks` và `api.vietqr.io/v2/ios-app-deeplinks` – danh sách app ngân hàng hỗ trợ deeplink (tối đa 2 lần/ngày). Không gửi dữ liệu đơn hàng.
* `dl.vietqr.io` – chỉ khi khách bấm nút "Mở app ngân hàng". Dữ liệu gửi đi: mã ngân hàng, số tài khoản, tên tài khoản, số tiền, nội dung chuyển khoản và địa chỉ trang đặt hàng thành công để quay lại.
* `cdn.vietqr.io` – logo ngân hàng và app ở trang thanh toán (một số logo app có thể lấy từ kho ứng dụng, ví dụ `play-lh.googleusercontent.com`).

Điều khoản sử dụng và chính sách bảo mật: https://www.vietqr.io

== Frequently Asked Questions ==

= Plugin có miễn phí không? =

Có. Plugin, mã QR và deeplink đều miễn phí. Tiền được chuyển thẳng từ tài khoản của khách vào tài khoản của bạn; VietQR không giữ hay can thiệp vào dòng tiền.

= App ngân hàng nào mở ra với thông tin điền sẵn? =

Các app có tag "Tự động điền" ở trang thanh toán (hiện gồm MB Bank, VietinBank iPay, BIDV SmartBanking, ACB ONE và OCB OMNI). Danh sách lấy từ VietQR.io và sẽ tăng thêm khi các ngân hàng hỗ trợ. Các app khác vẫn mở được, khách quét ảnh QR đã lưu trong app.

= Plugin có tự động xác nhận thanh toán không? =

Không. Đơn hàng ở trạng thái tạm giữ cho tới khi bạn xác nhận đã nhận tiền; nội dung chuyển khoản có mã đơn hàng nên rất dễ đối chiếu. Nếu muốn đơn hàng tự động xác nhận, hãy dùng [payOS](https://wordpress.org/plugins/payos/) (có gói miễn phí trọn đời) hoặc [Casso Flow](https://wordpress.org/plugins/casso-tu-dong-xac-nhan-thanh-toan-chuyen-khoan-ngan-hang/).

= Plugin hỗ trợ đơn vị tiền tệ nào? =

Mã VietQR dùng đồng Việt Nam nên phương thức thanh toán chỉ hiển thị khi cửa hàng dùng VND. Lập trình viên có thể thay đổi bằng filter `vietqr_supported_currencies`.

= Có chọn được logo ngân hàng hiển thị ở trang thanh toán không? =

Trang thanh toán hiển thị vài ngân hàng phổ biến và ô "+N ngân hàng & ví khác". Dùng filter `vietqr_featured_bank_bins` để trả về danh sách mã BIN ngân hàng bạn muốn.

= Nút "Mở app ngân hàng" hoạt động thế nào? =

Trên điện thoại, trang thanh toán hiển thị các app ngân hàng hỗ trợ VietQR Deeplink. Bấm vào một app, app mở ra với thông tin chuyển khoản đã điền sẵn, khách chỉ cần xác nhận. Trên máy tính, khách quét mã QR. Có thể tắt các nút này trong phần cài đặt; lập trình viên có thể đổi danh sách bằng filter `vietqr_deeplink_apps`.

== Screenshots ==

1. Trên điện thoại: thông tin chuyển khoản kèm nút sao chép và các nút "Mở app ngân hàng". App có tag "Tự động điền" mở ra với số tài khoản, số tiền, nội dung đã điền sẵn
2. Trên điện thoại: mã VietQR và nút "Lưu ảnh QR"
3. Trên máy tính: mã QR bên cạnh thông tin chuyển khoản
4. Ở bước chọn phương thức thanh toán: VietQR với các ngân hàng phổ biến và ô "+N ngân hàng & ví khác"
5. Trang cài đặt: ngân hàng, số tài khoản và tuỳ chọn deeplink
6. Email đơn hàng: mã QR và thông tin chuyển khoản
7. Toàn bộ email đơn hàng gửi cho khách

== Changelog ==

= 4.0.6 =
* Cập nhật danh sách người đóng góp
* Dọn cảnh báo của Plugin Check

= 4.0.5 =
* Giao diện thanh toán (mã QR, nút mở app ngân hàng, email) dùng chung mã nguồn với plugin Casso Flow, giao diện không đổi
* Template ghi đè chuyển sang thư mục shared/templates/ trong plugin, theme vẫn ghi đè tại yourtheme/vietqr/ như cũ

= 4.0.4 =
* Tên mặc định mới "Chuyển khoản ngân hàng", đồng nhất với Casso Flow và payOS. Cửa hàng đang dùng tên mặc định cũ được tự cập nhật, tên tự đặt giữ nguyên
* Kèm mã nguồn JavaScript của Checkout Block (thư mục src/)

= 4.0.3 =
* Sửa bảng so sánh bị vỡ trên trang plugin (WordPress.org không hỗ trợ bảng)

= 4.0.2 =
* Trang plugin bằng tiếng Việt, thêm bảng so sánh VietQR, Casso Flow và payOS

= 4.0.1 =
* Trang plugin mới: ảnh bìa, mô tả và ảnh chụp màn hình giới thiệu VietQR Deeplink

= 4.0.0 =
* Mới: nút "Mở app ngân hàng" trên điện thoại bằng VietQR Deeplink, điền sẵn số tài khoản, số tiền và nội dung (có thể tắt trong cài đặt)
* Giao diện thanh toán mới: tối ưu cho điện thoại, đồng bộ phong cách WordPress 7, nút sao chép và nội dung chuyển khoản nổi bật
* Mã QR dùng mẫu "payos" mới của VietQR
* CSS và JavaScript được cô lập: class có tiền tố vietqr, không dùng id, không có biến JavaScript toàn cục, không bị style của theme làm vỡ
* Khách xem lại mã QR tại Tài khoản > Xem đơn hàng khi đơn đang chờ thanh toán
* Nút "Lưu ảnh QR" lưu được ảnh thật: menu chia sẻ trên điện thoại, tải file trên máy tính
* Tách template email HTML và văn bản thuần; email văn bản thuần không còn lẫn HTML
* Chạy được khi WooCommerce được kích hoạt cho toàn mạng (multisite)
* Dùng selectWoo có sẵn trong WooCommerce thay cho bản Select2 cũ
* Gỡ bỏ tuỳ chọn Developer ID và kết nối tới vietqr.devgioi.com
* Thay đổi không tương thích ngược: template templates/bank-details.php và tên class CSS đã đổi. Theme ghi đè template hoặc style theo class cũ (list-bank, #qrcode, #downloadQR...) cần cập nhật lại

= 3.8.0 =
* Trang thanh toán chỉ hiển thị vài ngân hàng phổ biến kèm ô "+N ngân hàng & ví khác" thay vì toàn bộ logo
* Chỉ hiển thị phương thức thanh toán khi cửa hàng dùng VND
* Số tiền ở trang đặt hàng thành công và email được định dạng theo tiền tệ của cửa hàng
* Mã QR và thông tin chuyển khoản chuyển sang template có thể ghi đè (yourtheme/vietqr/bank-details.php)
* Script Checkout Block được build lại bằng @wordpress/scripts, nhẹ hơn và tương thích WordPress cũ hơn
* Toàn bộ chuỗi dùng text domain vietqr, cập nhật bản dịch tiếng Việt
* Yêu cầu WordPress 6.2 và PHP 7.4

= 3.7.0 =
* Trang thanh toán vẫn hoạt động khi máy chủ VietQR không phản hồi: dùng danh sách ngân hàng đã lưu hoặc có sẵn trong plugin
* Tải danh sách ngân hàng nhanh hơn, không gọi API ở mỗi lượt tải trang
* Mã QR hiển thị đúng với tên tài khoản có dấu hoặc ký tự đặc biệt
* Bật sẵn phương thức thanh toán khi cài mới
* Tương thích WooCommerce HPOS (High-Performance Order Storage)
* Tăng cường bảo mật khi hiển thị dữ liệu

= 3.6.0 =
* Kiểm tra tương thích WordPress 7.1 và WooCommerce 11.2
* Gói phát hành gọn hơn, không còn file nội bộ

= 3.5.0 =
* Hỗ trợ giao diện checkout mới (Checkout Block) của WooCommerce

= 2.1.1 =
* Sửa lỗi chính tả

= 2.1 =
* Hỗ trợ thêm 20 ngân hàng

= 1.7 =
* Sửa lỗi tương thích với một số theme

= 1.5 =
* Sửa lỗi hiển thị mã QR trong email

= 1.4 =
* Cache kết quả gọi API để tăng tốc

= 1.3 =
* Cải thiện hiệu năng

= 1.2 =
* Hiển thị nút tải mã QR trên điện thoại

= 1.0.0 =
* Phiên bản đầu tiên
* Hỗ trợ 50 ngân hàng Việt Nam
