# Ornament Amazon Automation

## Mục tiêu

Tự động hóa workflow `Ornament Amazon` theo hướng độc lập hoàn toàn với `Ornament Amazon 2`, nhưng vẫn giữ UX theo cùng chuẩn vận hành của Offorest.

## Route và module

- Trang chính: `/offorest/ornament-ornament`
- Product slug nội bộ: `ornament`
- Module UI/logic: `app/Livewire/Pages/OrnamentAmazon/*`, `app/Services/OrnamentAmazon/*`, `resources/views/livewire/pages/ornament-amazon/*`

## Bảng dữ liệu automation

Automation state dùng bảng riêng:

- `data_ornament_amazon`

Mục đích:

- lưu trạng thái chạy của từng item
- lưu step hiện tại
- lưu lỗi nếu có
- tránh làm nặng `product_design_assets`

Ảnh vẫn lưu như cũ trong `product_design_assets`.

## Flow hiện tại

Điều kiện đầu vào:

1. Item phải có `2. Main Image`

Sau khi user bấm `Duyet Main & Auto`, hệ thống chạy tuần tự:

1. `3. Script`
2. `4. Person A`
3. `4. Person B`
4. `5. Prompt create`
5. `6. Mockup`

Sau khi xong `6. Mockup`:

- item được approve tự động
- queue upload Drive được sync tự động

## Trạng thái step

Mỗi step có thể ở một trong các trạng thái:

- `wait`
- `running`
- `done`
- `error`

Mapping hiển thị trên Catalog:

- `Wait`
- `Running`
- `Done`
- `Error`

## UI tracking

### Trên từng card item

- step đang chạy sẽ hiện spinner/badge nhỏ
- nếu step đã có ảnh thì vẫn giữ ảnh hiển thị
- `3. Script`, `4. Person A/B`, `5. Prompt create`, `6. Mockup` đều có trạng thái running riêng

### Trên Catalog

Có bảng `Catalog Automation Log` hiển thị:

- `ID`
- `3. Script`
- `4. Person A`
- `4. Person B`
- `5. Prompt`
- `6. Mockup`
- `Tổng trạng thái`

## Queue / worker

Automation dùng queue job.

Lệnh cần chạy trong local/server:

```bash
php artisan queue:work
```

Migration cần có:

```bash
php artisan migrate
```

Nếu chưa có bảng `data_ornament_amazon`, service sẽ chặn automation và báo rõ cần migrate.

## Quy ước làm việc tiếp theo

Khi sửa `Ornament Amazon`:

- ưu tiên giữ độc lập với `Ornament Amazon 2`
- không tái dùng chéo state automation giữa 2 module
- nếu đổi flow step, phải cập nhật cả:
  - `docs/memory.md`
  - `docs/ornament-amazon-automation.md`
  - UI catalog log nếu có thay đổi tên step/trạng thái
