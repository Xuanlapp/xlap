# 🧠 Memory & Context - Important Information

**Mục đích:** Lưu trữ các thông tin quan trọng, rules, và context mà AI cần luôn nhớ khi làm việc với project này.

---

## ⚡ Critical Rules

### Development Rules
- ✅ **Always add PHPDoc comments** khi tạo method mới
- ✅ **Use type hints** - `public function getName(): string`
- ✅ **Validate inputs** - Không bao giờ trust user input
- ✅ **Use transactions** khi modify multiple related records
- ✅ **Log important actions** - Invoice sent, payment received, etc.
- ❌ **Never hardcode secrets** - Sử dụng .env file
- ❌ **Never DELETE data** - Soft delete hoặc archive thay vì

### Naming Conventions
- **Class names:** `PascalCase` - `InvoiceTracker`, `EmailService`
- **Method names:** `camelCase` - `getInvoices()`, `sendEmail()`
- **Properties:** `camelCase` - `$invoiceAmount`, `$dueDate`
- **Constants:** `UPPER_SNAKE_CASE` - `MAX_RETRIES`, `API_TIMEOUT`

### File Organization
- Models → `app/Models/`
- Controllers → `app/Http/Controllers/`
- Livewire Components → `app/Livewire/`
- Business Logic → `app/Actions/` hoặc `app/Services/`
- Views → `resources/views/`
- Documentation → `docs/`

---

## 🎯 Key Business Rules

### Invoice Management
| Status | Meaning | Action |
|--------|---------|--------|
| Draft | Chưa hoàn thành | Có thể edit hoặc delete |
| Sent | Đã gửi cho client | Chờ thanh toán |
| Paid | Đã nhận thanh toán | Archive |
| Overdue | Quá hạn | Gửi reminder/alert |

### Monthly Close
- **Deadline:** Last day of month
- **Process:** Lock → Reconcile → Report → Archive
- **Checks:** All invoices must be accounted for
- **Notification:** Slack alert when complete

### Tax Preparation
- **Quarterly:** Q1, Q2, Q3, Q4
- **Annual:** December 31st deadline
- **Documents needed:** Invoices, Expenses, Receipts, Payments
- **Export format:** PDF, Excel compatible

---

## 🔄 Common Data Relationships

```
User (1) ──→ (M) Invoices
           ──→ (M) Contracts
           ──→ (M) Activities

Invoice (1) ──→ (M) Payments
          ──→ (1) Client

Contract (1) ──→ (M) Amendments
          ──→ (1) Party1
          ──→ (1) Party2

BusinessMetrics (1) ──→ (1) Month
                   ──→ (M) Transactions
```

---

## 🔧 External Integrations

### QuickBooks
- **Purpose:** Sync financial data
- **Auth:** OAuth 2.0
- **Rate Limit:** 500 requests/hour
- **Sync Frequency:** Every 6 hours
- **Docs:** `docs/connectors/quickbooks.md`

### Gmail
- **Purpose:** Send invoices, reminders
- **Auth:** OAuth 2.0 (service account)
- **Rate Limit:** 1000 emails/day
- **Template Storage:** `resources/views/emails/`
- **Docs:** `docs/connectors/gmail.md`

### Slack
- **Purpose:** Notifications & alerts
- **Auth:** Bot token
- **Channels:** #business-alerts, #invoices, #team-updates
- **Rate Limit:** No strict limit
- **Docs:** `docs/connectors/slack.md`

### HubSpot
- **Purpose:** Client & contact management
- **Auth:** API key
- **Sync:** Clients ↔ HubSpot contacts
- **Rate Limit:** 100 requests/10 seconds
- **Docs:** `docs/connectors/hubspot.md`

---

## 🧭 Source of Truth for Project Tree

- Khi cần hiểu cấu trúc project hiện tại, đọc [docs/architecture.md](docs/architecture.md) trước.
- File [docs/CLAUDE.md](docs/CLAUDE.md) là tài liệu AI tổng quan, còn [docs/architecture.md](docs/architecture.md) là tree chuẩn để bám theo.
- Nếu có thay đổi cấu trúc thư mục, cập nhật đồng thời cả hai file.

## 📊 Key Metrics & KPIs

```
Revenue Metrics:
├── Monthly Revenue
├── Average Invoice Value
├── Revenue per Client
└── Trending (MoM Growth)

Operational Metrics:
├── Invoice Count (monthly)
├── Paid Invoice Rate (%)
├── Average Days to Payment
└── Overdue Invoice Count

Financial Health:
├── Cash on Hand
├── Accounts Receivable
├── Accounts Payable
└── Profit Margin (%)
```

---

## 🛠️ Tech Stack Details

### Backend
- **Framework:** Laravel 11
- **Component Library:** Livewire 3.x
- **Database:** MySQL/PostgreSQL
- **Auth:** Laravel Sanctum / Session-based
- **Queue:** Redis (for async tasks)

## Ornament Amazon Automation Notes

- `Ornament Amazon` đã tách riêng khỏi `Ornament Amazon 2` và dùng route `/offorest/ornament-ornament`.
- Automation state được lưu riêng trong bảng `data_ornament_amazon` để không làm nặng `product_design_assets`.
- Quy trình automation hiện tại: `2. Main Image` → `3. Script` → `4. Person A` → `4. Person B` → `5. Prompt create` → `6. Mockup`.
- Trạng thái step có 4 loại: `wait`, `running`, `done`, `error`.
- Catalog trên trang Ornament Amazon hiển thị log theo từng item/step để user theo dõi.
- Khi step lỗi, automation pause và user có thể bấm tiếp tục để chạy tiếp từ step kế.
- Automation dùng queue, nên cần worker `php artisan queue:work` hoặc môi trường tự chạy worker nền.
- Ảnh output vẫn lưu theo cơ chế hiện tại của `product_design_assets`, chỉ state/log automation chuyển sang bảng riêng.

### Frontend
- **UI Framework:** Tailwind CSS
- **JS Framework:** Alpine.js (lightweight interactivity)
- **Build Tool:** Vite
- **Icons:** Heroicons

### DevOps
- **Hosting:** (To be specified)
- **Database:** MySQL
- **File Storage:** Local or S3
- **Monitoring:** (To be specified)

---

## 🔒 Environment Variables Needed

```env
# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=xlap_tech
DB_USERNAME=root
DB_PASSWORD=

# External APIs
QUICKBOOKS_CLIENT_ID=xxx
QUICKBOOKS_CLIENT_SECRET=xxx
GMAIL_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
SLACK_BOT_TOKEN=xoxb-xxx
HUBSPOT_API_KEY=xxx

# App
APP_ENV=local
APP_DEBUG=true
APP_KEY=base64:xxx
APP_URL=http://localhost:8000
```

---

## 📞 Contact & Escalation

**Code Questions:** Check `docs/CLAUDE.md` first  
**Business Logic Questions:** Check relevant `docs/business-processes/*/README.md`  
**API Integration Issues:** Check `docs/connectors/*.md`  
**Unknown Issues:** Check `memory.md` then `CLAUDE.md`

---

## 🔄 Common Commands

```bash
# Development
php artisan serve                  # Start dev server
npm run dev                        # Vite watch mode
php artisan tinker                # PHP REPL

# Database
php artisan migrate               # Run migrations
php artisan seed                  # Run seeders
php artisan migrate:fresh         # Fresh database

# Testing
php artisan test                  # Run PHPUnit tests

# Livewire
php artisan livewire:make ComponentName  # Create component
php artisan livewire:make Actions/MyAction --action  # Create action

# Cache & Optimization
php artisan cache:clear           # Clear all cache
php artisan config:cache          # Cache config
php artisan route:cache           # Cache routes
```

---

## ⏰ Time Zones & Scheduling

- **Primary Time Zone:** UTC or Asia/Ho_Chi_Minh (to be confirmed)
- **Business Hours:** 9 AM - 6 PM
- **Invoice Reminders:** Sent at 10 AM
- **Daily Reports:** Generated at 8 AM
- **Monthly Close:** Last day of month, 5 PM

---

## 📋 Checklists

### Before Deploying Code
- [ ] All tests passing
- [ ] No console errors
- [ ] No database errors
- [ ] PHPDoc comments added
- [ ] Error handling implemented
- [ ] Performance tested

### Before Release
- [ ] Database migrations ready
- [ ] .env variables documented
- [ ] API endpoints tested
- [ ] External integrations verified
- [ ] Security audit passed
- [ ] User documentation updated

---

## Sticker PSD Mockup Memory

- Source of truth: [docs/sticker-psd-mockup.md](sticker-psd-mockup.md).
- Chuc nang hien nam o Sticker card, cot `3. Mockup Tu Chon`.

## Image Preview Performance Memory

- Direct image URLs with browser-renderable extensions should render directly in the browser instead of going through Laravel `image-preview`, so large sticker/ornament lists do not make the app server proxy every thumbnail.
- Google Drive UI previews use thumbnail URL size `w800`; Vertex/source-fetch logic can still use its own higher resolution path when needed for generation.
- Card thumbnails should use `loading="lazy"`, `decoding="async"`, and `fetchpriority="low"` so hidden/offscreen images do not block tab switching and page rendering.
- Do not include frequently changing filter/search values in Livewire component `key` for image-heavy lists. Use reactive child props and stable keys per tab/status so existing image DOM can be preserved.
- Google Drive public thumbnail URLs are not reliable enough by themselves. `image-preview` should fall back to authenticated Google Drive API download when Drive returns HTML/non-image/failed thumbnail responses.

## Vertex Rate Limit Memory

- Vertex image generation must be protected from burst clicks. `VertexImageGenerator` uses a per-credential cache lock so only one `generateContent` request runs per Vertex key at a time.
- The default behavior should be queue-like for clicks: later Livewire requests wait on the per-credential lock instead of failing fast, so spinners can keep running and images generate sequentially.
- When Vertex returns `429` or `RESOURCE_EXHAUSTED`, the credential enters cooldown using `VERTEX_COOLDOWN_SECONDS` (or `Retry-After` if Google sends it). New clicks should fail fast with a wait message instead of sending more API calls.
- For high throughput, the next architectural step is queueing generation jobs and running a small fixed number of workers per credential/project; do not rely on unlimited parallel Livewire requests.
- User co the luu nhieu PSD, nhung moi user/product/function chi co 1 PSD active.
- Sticker custom PSD dung `function_key = sticker_custom_mockup`.
- Renderer command mac dinh: `PSD_MOCKUP_RENDERER_COMMAND="node scripts/psd-renderer/render.js"`.
- PSD can co layer `Design` hoac `Desgin` va cac folder `MOCKUP 1`, `MOCKUP 2`, ...
- Dau vao render la anh master tu `redesign` cua o `2. Create Master`.
- PNG render xong replace lai tu `mockup1` den `mockup11`; moi lan bam Generate + Update cho PSD se cap nhat DB bang output moi va clear slot mockup cu khong con trong output.
- Sticker khong dung Lifestyle Image trong UI; PSD Mockup Tu Chon dung `mockup1` den `mockup11`.
- Khi render PSD, clear mockup cu va ghi output moi theo thu tu tu `mockup1`.
- Sticker item chi duoc duyet khi co it nhat mot mockup. DB luu `is_approved` va `approved_at`.
- Sticker item chi duoc edit source detail khi chua co `redesign`. Sau khi da tao anh `2. Create Master`, UI an nut `Edit item` va backend chan sua source detail.
- Sticker item da co bat ky `mockup1..mockup11` nao thi khong duoc tao lai/chon lai `2. Create Master`. UI an nut `Create Master` va review Master khong hien action chon lai; backend chan `generateRedesign` va `selectRedesign`.
- Sticker list co 3 filter: `all`, `unapproved` (gom ca chua co master va da co master nhung chua duyet), `approved`.
- Sticker list co `StickerStatusPanel` rieng cho tung tab va pagination query DB rieng. Alpine chi an/hien panel, khong request parent khi click tab.
- Moi Sticker panel dung Livewire lazy-load lan dau khi mo tab va giu mounted sau do. Vi vay card dang generate van tiep tuc chay khi user chuyen tab; quay lai tab cu khong mat spinner/state. Parent lay PSD active mot lan va truyen ten xuong cac card de tranh query lap khi render nhieu card.
- Preview local `/storage/...` can co cache-bust theo `filemtime` de khong bi browser giu anh render lan dau.
- UI chi hien slot mockup co output that, khong hien placeholder mockup trong.
- O `3. Mockup Tu Chon` phai dung cung `aspect-[4/4.45]` voi cac o 1, 2 va scroll noi bo trong khung.
- Sticker giu lich su anh `2. Create Master` trong `product_design_assets.redesign_candidates`; bam Create Master nhieu lan khong mat anh cu.
- Khi review anh Create Master, user co the chon anh dang xem lam `redesign` hien tai hoac tao Sticker item moi tu anh do. Modal Add Item phai prefill keyword/image link va hien preview anh.
- Khi review nhieu anh mockup, modal review ho tro previous/next trong gallery.
- Khong bat trim mac dinh cho design vi co the lam anh bi phong to: `OFFOREST_TRIM_MOCKUP_DESIGN=false`.
- Khi thay Design vao PSD, lay alpha bounds cua Design goc lam target rect, nhan `OFFOREST_MOCKUP_DESIGN_SCALE` mac dinh `0.72`, trim transparent bounds cua master, roi fit dong ti le vao fit rect; khong stretch va khong phong to ra ngoai vung Design goc.
- Renderer phai render full PSD stack va toggle tung group `MOCKUP *`; khong render rieng children cua group.
- Khi replace Design, chi thay `designLayer.canvas`, giu metadata `effects`, `opacity`, `blendMode`, `mask`, `placedLayer`.

## Ornament Memory

- Ornament copy y chang workflow Sticker, nhung dung product slug `ornament`.
- Ornament co page/modal/service rieng trong `app/Livewire/Pages/Ornament`, `app/Livewire/Modals/Ornament`, `app/Services/Ornament`.
- Ornament generated output dung `generated/ornament/redesign`, `generated/ornament/final`, va `generated/ornament/mockups/{assetId}`.
- Ornament PSD function key dung `ornament_custom_mockup`, tach rieng voi Sticker PSD.

## Google Drive Export Memory

- Approved image export command: `php artisan offorest:upload-approved-images-to-drive`.
- Schedule chay moi ngay luc 22:00 server time trong `routes/console.php`.
- Admin co nut `Upload images to Drive` o trang Admin de chay ngay lap tuc.
- Chi upload cac image column local bat dau bang `/storage/` cua item `is_approved = true`.
- Sau khi upload thanh cong, DB column duoc thay bang Google Drive URL, set `drive_uploaded_at`, roi xoa file local tuong ung de giam dung luong may.
- Khi export Drive cho approved asset, xoa cac file local trong `redesign_candidates` va clear `redesign_candidates` trong DB de giam dung luong may; cac candidate nay khong tinh vao so anh upload chinh.
- Google Drive upload uu tien OAuth 2.0 connection trong `google_drive_connections`; service account chi la fallback cu.
- Admin connect OAuth tai `/offorest/admin/google-drive/connect`, callback `/offorest/admin/google-drive/callback`.
- Config OAuth can co `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, `GOOGLE_DRIVE_FOLDER_ID`; tuy chon `GOOGLE_DRIVE_REDIRECT_URI`, `GOOGLE_DRIVE_MAKE_PUBLIC=true`, `GOOGLE_DRIVE_SCOPES=https://www.googleapis.com/auth/drive.file`.

## Activity Log Memory

- Audit log table: `activity_logs`.
- Admin-only logs page: `/offorest/admin/logs`, route `offorest.admin.logs`.
- Log service: `App\Services\Logging\ActivityLogService`.
- Log cac event user/admin/system quan trong, gom Drive export image-level va batch summary.
- Drive export ghi `drive_export.image_uploaded` cho tung anh va `drive_export.completed` cho moi batch.

## API Secret Security Memory

- Vertex `private_key` va `credentials_json` dung encrypted cast. `credentials_json` phai la text/longText vi ciphertext khong phai JSON hop le.
- Khong day raw external API response body ra toast/UI. Log server chi luu preview da redact token/key.
- Admin tao user co the cau hinh Vertex ngay tren form: `none`, `new` paste service account JSON, hoac `copy` tu user khac co Vertex active. Khong can them Vertex key truc tiep bang DB tool.

---

**Last Updated:** May 29, 2026  
**Version:** 1.0  
**Status:** Active

## Ornament Amazon separation
- `Ornament Amazon` keeps its own route slug `ornament-ornament` via `app/Support/ProductRegistry.php`.
- `DeleteIdeaConfirm` now treats Ornament Amazon as the `ornament` product slug only and does not trigger the extra hard-refresh path that caused 404 regressions.
- Ornament Amazon add/delete refreshes should stay event-driven like `Ornament Amazon 2`, while remaining independent for later workflow changes.
## Ornament Amazon 2 Automation Notes

- `Ornament Amazon 2` uses `data_ornament_amazon` for approval automation state, with `product_slug = ornament-amazon-2` and `workflow_name = ornament-amazon-two-automation`.
- Clicking `Duyet` starts the queued automation instead of doing all work inside the Livewire request.
- Queue job: `App\Jobs\RunOrnamentAmazonTwoAutomation`.
- Step order: `3. Script` -> `4. Person A` -> `4. Person B` -> `5. Prompt create` -> `6. Mockup` -> completed.
- Step state is stored in `step_data`; errors are stored in `step_errors` and `last_error`.
- If one item fails, only that item stops; other queued items continue.
- Item should only be considered fully approved after automation completes successfully.
- Related doc: `docs/ornament-amazon-two-automation.md`.
- Step `6. Mockup` now dispatches all slot jobs in parallel; each slot updates its own state and automation completes only after the batch finishes successfully.

- Only 6/6 mockup allows final approval; if any slot is still missing, the item stays failed at 6. Mockup so the user can retry.
- Retry at 6. Mockup must use persisted DB truth: keep existing mockup1..mockup6 outputs, clear/re-dispatch only missing slots, and never delete already successful mockups.




- Ornament Amazon 2 card button logic: Auto when script is empty, Continue when automation stops at Person A/B or Prompt create, Retry when mockup fails and DB still misses slots, Duyet only after automation completed and all 6 mockups exist in DB.

