# AI MEMORY

File này dùng để lưu lại quá trình AI đã làm trong project.
Trước khi làm tiếp, AI phải đọc file này trước.

---

## Quy tắc làm việc

- Luôn đọc file này trước khi sửa code.
- Sau khi sửa xong phải cập nhật lại file này.
- Ghi ngắn gọn, rõ ý, có tên file và logic đã thay đổi.
- Không xóa lịch sử cũ, chỉ thêm mục mới lên trên cùng hoặc cuối file.

---

## Nhật ký làm việc

### 2026-06-06

**Muc tieu:**  
Kiem tra vi sao user noi da doi proxy nhung Vertex van loi.

**File da sua/tao:**  
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Khong sua code.
- Kiem tra `.env`, Laravel config va IP outbound.

**Loi da gap va cach xu ly:**  
- `config('services.vertex.http_proxy')` dang `EMPTY`.
- IP outbound van la `171.227.40.92`.
- Trong `.env` chi co dong `# VERTEX_HTTP_PROXY=...` bi comment nen app khong dung proxy.

**Logic can nho:**  
- Dong `.env` bat dau bang `#` la comment, Laravel khong doc.
- Can them dong `VERTEX_HTTP_PROXY=...` khong co dau `#`, sau do `php artisan optimize:clear` va restart server.

**Viec can lam tiep:**  
- User can dien proxy moi vao `.env` bang bien `VERTEX_HTTP_PROXY`.

### 2026-06-06

**Muc tieu:**  
Fix tinh trang bam Create Master khong bao loi, quay mai.

**File da sua/tao:**  
- `app/Services/Vertex/VertexImageGenerator.php`
- `app/Livewire/Pages/Sticker/ProductDesignCard.php`
- `app/Livewire/Pages/Ornament/ProductDesignCard.php`
- `app/Livewire/Modals/Image/ReviewImage.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Xoa `dd($response)` con sot trong `VertexImageGenerator::generate()`; day la nguyen nhan lam Livewire dung request va UI quay mai.
- Xoa comment `// dd($payload)`.
- Them `catch (Throwable)` cho Sticker/Ornament generation va modal custom image de log loi bat ngo va hien toast thay vi treo UI.
- Chay `php artisan optimize:clear` va `php artisan cache:clear` de xoa Vertex cache lock con sot tu lan `dd`.

**Loi da gap va cach xu ly:**  
- `dd($response)` nam sau khi goi Vertex trong lock, lam request dung truoc khi Livewire dispatch finished event.
- Lock Vertex co the bi giu den TTL sau khi `dd`; da clear cache.
- Diagnostic that voi anh PNG 1x1 tra loi trong 1.44s: `HTTP 417`, khong treo.

**Logic can nho:**  
- Hien tai UI khong con quay vo han do code; neu Vertex fail thi toast se hien loi.
- Neu van `HTTP 417` thi la Google chan IP/proxy, khong phai spinner/code.

**Viec can lam tiep:**  
- Doi proxy/IP sach hoac dung moi truong VPS dang tao anh duoc.
- Sau khi doi `.env`, chay `php artisan optimize:clear` va restart web server.

### 2026-06-06

**Muc tieu:**  
Them duong di function vao man hinh `dd` Vertex de biet tao anh dang chay qua function nao.

**File da sua/tao:**  
- `app/Services/Vertex/VertexImageGenerator.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Khi `VERTEX_DEBUG_PAYLOAD=true`, `dd()` them `call_path`.
- `call_path` loc cac frame `App\...::function`, bo vendor internals de de doc.

**Loi da gap va cach xu ly:**  
- Khong co. `php -l app/Services/Vertex/VertexImageGenerator.php` pass.

**Logic can nho:**  
- `dd()` se dung o lan goi Vertex dau tien; xem `call_path` de biet luong Create Master/custom/final dang di qua service nao.

**Viec can lam tiep:**  
- Sau khi debug xong tat `VERTEX_DEBUG_PAYLOAD=false`.

### 2026-06-06

**Muc tieu:**  
Them cach `dd` du lieu payload gui len Vertex ra man hinh de debug.

**File da sua/tao:**  
- `app/Services/Vertex/VertexImageGenerator.php`
- `config/services.php`
- `.env.example`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them config `VERTEX_DEBUG_PAYLOAD=false`.
- Neu bat `VERTEX_DEBUG_PAYLOAD=true`, truoc khi post Vertex se `dd()` endpoint, proxy enabled, generationConfig, prompt preview/length, inline image mime type/base64 length/estimated bytes/data preview ngan.
- Khong dump full access token, private key, hoac full base64 anh.

**Loi da gap va cach xu ly:**  
- Khong co.

**Logic can nho:**  
- Bat debug payload chi dung local; sau khi xem xong phai tat lai `VERTEX_DEBUG_PAYLOAD=false`.
- Sau khi doi `.env` can `php artisan optimize:clear` va restart web server neu dang chay.

**Viec can lam tiep:**  
- User bat `VERTEX_DEBUG_PAYLOAD=true`, bam Create Master, chup/kiem tra man hinh dd.

### 2026-06-06

**Muc tieu:**  
Kiem tra toan bo va fix tiep loi `Khong ket noi duoc Vertex API...` khi tao anh.

**File da sua/tao:**  
- `app/Services/Vertex/VertexImageGenerator.php`
- `config/services.php`
- `.env.example`
- `tests/Unit/VertexImageGeneratorTest.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them resize/re-encode input image truoc khi inline vao Vertex de tranh request qua lon lam proxy reset.
- Them config `VERTEX_MAX_INPUT_DIMENSION`, `VERTEX_MAX_INLINE_IMAGE_BYTES`, `VERTEX_GOOGLE_DRIVE_THUMBNAIL_SIZE`.
- Giam Google Drive thumbnail mac dinh tu `w2000` xuong config mac dinh `w1200`.
- Gioi han output image qua lon va bo qua gan PPI neu file qua lon.
- Them endpoint builder: `global` dung `aiplatform.googleapis.com`, region khac dung `{region}-aiplatform.googleapis.com`.
- Them test cho proxy, optimize input image, va endpoint.

**Loi da gap va cach xu ly:**  
- Diagnostic text nho qua proxy va direct deu tra `HTTP 417 automated queries`, nen khong phai do anh input qua lon.
- Log co `cURL error 56 / unexpected eof` khi proxy ngat ket noi; code da giam payload anh de loai bo nguyen nhan request qua lon.
- Full test pass: `78 tests`, `77 passed`, `1 skipped`.

**Logic can nho:**  
- Code da toi uu het phan co the trong app: proxy, HTTP/1.1, no Expect, error detail, image downsize, endpoint dung theo region.
- Neu request text nho van `HTTP 417` thi day la IP/proxy/network bi Google chan, code khong the tu vuot qua.
- De tao anh that can proxy/IP sach hoac credential/project chay duoc tu moi truong hien tai.

**Viec can lam tiep:**  
- Doi proxy/IP khac hoac bo proxy neu network sach.
- Neu het 417 ma gap 403 thi cap IAM/model access hoac copy credential Vertex dang chay duoc tren VPS.
- Sau khi doi `.env` phai `php artisan optimize:clear` va restart web server.

### 2026-06-06

**Muc tieu:**  
Fix loi UI bao `Vertex API loi. Hay kiem tra quota, credential hoac cau hinh model` khi VPS tao anh duoc nhung may local fail.

**File da sua/tao:**  
- `app/Services/Vertex/VertexImageGenerator.php`
- `config/services.php`
- `.env.example`
- `tests/Unit/VertexImageGeneratorTest.php`
- `tests/Feature/OfforestProductSchemaTest.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Khoi phuc ho tro `VERTEX_HTTP_PROXY` cho token Google va Vertex `generateContent`.
- Them header `Expect: ''`, user-agent va HTTP/1.1 options de giam loi upload/proxy.
- Bat `ConnectionException` va tra loi ro rang hon.
- Doi loi generic thanh message co HTTP status va `error.message` cua Google.
- Them test proxy config.
- Sua 2 test admin Vertex de ten user co slug `Sticker`, dung rule product access hien tai.

**Loi da gap va cach xu ly:**  
- Diagnostic that qua proxy Cloudzone tra `HTTP 417 automated queries` cho ca credential image va marketplace: proxy IP `103.67.196.83` bi Google chan/flag.
- Log khi khong qua proxy co `HTTP 403 IAM_PERMISSION_DENIED` voi project `velvety-carving-494308-q6`: service account local thieu quyen `aiplatform.endpoints.predict` hoac model/project khong co access.
- Full test ban dau fail do fixture ten user; da sua.

**Logic can nho:**  
- Code da doc proxy tu `services.vertex.http_proxy`.
- Neu UI bao `HTTP 417` thi doi proxy/IP sach hon; code khong the vuot Google block.
- Neu UI bao `HTTP 403 Permission 'aiplatform.endpoints.predict' denied` thi can cap IAM `Vertex AI User`/model access cho service account, hoac copy dung credential dang chay duoc tren VPS.
- VPS chay duoc khong chung minh local credential/IP chay duoc; local dang dung DB credential rieng.

**Viec can lam tiep:**  
- De tao anh that tren local: dung proxy/IP khac khong bi Google chan va dung Vertex credential co quyen giong VPS.
- Sau khi doi proxy/credential, chay `php artisan optimize:clear` va restart server web.

### 2026-06-06

**Muc tieu:**  
Quay lai code push moi nhat tren remote, xoa cac thay doi code local da lam.

**File da sua/tao:**  
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Chay `git fetch origin`.
- Chay `git reset --hard origin/main`.
- Tracked code da quay ve commit `c0552df upadte generate-listing-metadata`.

**Loi da gap va cach xu ly:**  
- PowerShell khong ho tro `&&`; da chay `git fetch origin` va `git reset --hard origin/main` thanh 2 lenh rieng.

**Logic can nho:**  
- Sau reset, `git status` chi con `AI_MEMORY.md` untracked.
- Khong xoa `AI_MEMORY.md` vi day la file memory project user yeu cau giu.

**Viec can lam tiep:**  
- Neu user muon repo sach tuyet doi nhu remote, can xoa hoac add/commit `AI_MEMORY.md`.

### 2026-06-06 13:51:45 +07:00

**Muc tieu:**  
Kiem tra proxy Cloudzone co lam doi IP cho request Vertex/Laravel khong sau khi van gap HTTP 417.

**File da sua/tao:**  
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Khong sua code.
- Xac minh `.env` co `VERTEX_HTTP_PROXY`.
- Kiem tra `curl -x` qua proxy tra IP `103.67.196.83`.
- Kiem tra Laravel HTTP client voi `config('services.vertex.http_proxy')` cung tra IP `103.67.196.83`.

**Loi da gap va cach xu ly:**  
- Van gap `Vertex API loi khi tao anh. HTTP 417: Google dang tu choi request...`
- Ket luan proxy da duoc app doc va da doi IP; neu con 417 thi IP proxy Cloudzone nay cung bi Google chan/flag, hoac web process cu can restart.

**Logic can nho:**  
- `VERTEX_HTTP_PROXY` dang hoat dong trong Laravel runtime.
- `aiplatform.googleapis.com` ket noi duoc qua proxy, nhung Vertex POST co the van bi Google chan theo IP/reputation.

**Viec can lam tiep:**  
- Restart tien trinh web/PHP dang chay roi test lai.
- Neu van 417, doi sang proxy khac, uu tien residential/ISP hoac IP sach hon; datacenter/shared proxy co kha nang bi Google chan.

### 2026-06-06 13:32:54 +07:00

**Muc tieu:**  
Kiem tra vi sao Create Master va custom anh khong tao duoc, xac minh co lien quan 300 PPI hay khong.

**File da sua/tao:**  
- `tests/Feature/OfforestProductSchemaTest.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi ten user trong 2 test admin Vertex thanh co chu `Sticker` de dung rule ten user phai chua product slug.
- Chay `php artisan optimize:clear` de clear config/cache/view.

**Loi da gap va cach xu ly:**  
- Log Laravel co loi cu `Call to undefined method VertexImageGenerator::sourceImagePartAttempts()`; code hien tai da co method nay.
- Log Laravel co loi Vertex `HTTP 417 automated queries` va `cURL error 55/56`; day la loi Google/network/IP/proxy khi goi Vertex, khong phai do 300 PPI.
- Full feature test ban dau fail do test fixture khong dung rule user/product; da sua test name.

**Logic can nho:**  
- Create Master va custom variation deu di qua `App\Services\Vertex\VertexImageGenerator`.
- PSD custom mockup chi render sau khi asset da co `redesign`; neu Master fail thi custom PSD cung khong co dau vao.
- `OUTPUT_PPI = 300` chi gan metadata pHYs khi luu PNG; khong phai nguyen nhan chinh khien Vertex khong tra anh.
- Neu gap `HTTP 417 automated queries` thi can doi network/IP/proxy hoac set `VERTEX_HTTP_PROXY`; code da co config `services.vertex.http_proxy`.

**Viec can lam tiep:**  
- Neu tren may that van fail, xem toast/log de phan biet `HTTP 417`, quota `429`, hay connection reset.
- Muon test tao anh that can dung credential/network Vertex hoat dong; test tu dong hien tai fake HTTP nen khong ton quota.

### 2026-06-06

**Mục tiêu:**  
Tạo hệ thống memory để AI nhớ quá trình đã làm.

**File đã sửa/tạo:**  
- `AI_MEMORY.md`

**Thay đổi chính:**  
- Tạo file memory ở thư mục gốc project.
- Thêm quy tắc bắt buộc đọc memory trước khi sửa code và cập nhật sau khi hoàn thành.

**Lỗi đã gặp và cách xử lý:**  
- Không có.

**Logic cần nhớ:**  
- Mỗi lần AI làm xong phải ghi lại quá trình.
- Lần sau AI đọc file này trước để không hỏi lại những phần đã rõ.

**Việc cần làm tiếp:**  
- Áp dụng file này cho từng lần làm việc tiếp theo trong project/code.

### 2026-06-12 09:04:30 +07:00

**Muc tieu:**  
Cho Sticker "mockup tu chon" vao hang doi de VPS yeu khong render nhieu PSD cung luc.

**File da sua/tao:**  
- `app/Services/Sticker/PsdMockupRenderer.php`
- `config/services.php`
- `.env.example`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Boc `PsdMockupRenderer::render()` bang Laravel cache lock.
- Chi cho 1 render PSD sticker chay tai mot thoi diem; request sau se doi request truoc chay xong roi moi render.
- Them config `PSD_MOCKUP_RENDERER_LOCK_SECONDS` va `PSD_MOCKUP_RENDERER_WAIT_SECONDS`.

**Loi da gap va cach xu ly:**  
- Patch `.env.example` lan dau fail vi file co 2 dong `PSD_MOCKUP_RENDERER_COMMAND`; da them bien lock/wait vao ca block chinh va block `mockup tu chon`.

**Logic can nho:**  
- Lock key: `sticker:psd-mockup-renderer:lock`.
- Mac dinh lock TTL 900s, thoi gian cho hang doi 1800s.
- Neu doi qua lau se bao loi: `Hang doi render PSD dang qua lau...`.
- Day la hang doi dong bo trong request Livewire, khong phai background queue worker.

**Viec can lam tiep:**  
- Neu co rat nhieu user bam cung luc va request bi timeout, nen chuyen sang Laravel Queue + worker rieng cho render PSD.
- Test da chay: `php -l app/Services/Sticker/PsdMockupRenderer.php`, `php -l config/services.php`, `php artisan test tests/Feature/OfforestProductSchemaTest.php`, `php artisan test`, `php artisan optimize:clear`.

### 2026-06-12 11:14:29 +07:00

**Muc tieu:**  
Kiem tra vi sao admin va user da chinh cung Vertex key nhung admin van tao anh loi 403.

**File da sua/tao:**  
- `app/Services/Vertex/VertexImageGenerator.php`
- `tests/Unit/VertexImageGeneratorTest.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Sua `credentialsFor()` de cot `client_email` va `private_key` trong `vertex_api_credentials` override `credentials_json`.
- Them test dam bao khi `credentials_json` cu khac key, service van dung cot key hien tai.

**Loi da gap va cach xu ly:**  
- Admin record id=1 co cot `project_id/client_email/private_key` da giong user, nhung `credentials_json` van la JSON cu: project `psychic-cursor-494308-i8`, email `nhom5pc@...`.
- Code cu dung `??=` nen neu JSON co `client_email/private_key` thi no thang cot hien tai, lam admin ky token bang key cu roi goi project moi `velvety-carving-494308-q6`, dan den HTTP 403 `aiplatform.endpoints.predict`.
- Da doi sang uu tien cot explicit, `php artisan optimize:clear`.

**Logic can nho:**  
- `VertexImageGenerator::generate()` lay credential theo user dang login va function_key `image_generation`.
- Neu admin/user "nhin nhu cung key" nhung van khac, kiem tra ca `credentials_json` vi truoc day JSON cu co the override cot.
- Sau fix, cot `client_email/private_key` la nguon uu tien; JSON chi lam fallback/metadata.

**Viec can lam tiep:**  
- Neu van 403 sau fix, luc do la IAM thuc su cua service account/project/model, khong con do JSON cu override.
- Test da chay: `php -l app/Services/Vertex/VertexImageGenerator.php`, `php artisan test tests/Unit/VertexImageGeneratorTest.php`, `php artisan test`, `php artisan optimize:clear`.

### 2026-06-12 15:04:01 +07:00

**Muc tieu:**  
Gui loi user action ve Telegram bot, gom user nao bi loi gi va du lieu dang thao tac.

**File da sua/tao:**  
- `app/Services/Monitoring/TelegramErrorReporter.php`
- `app/Livewire/Concerns/ReportsUserActionErrors.php`
- `bootstrap/app.php`
- `config/services.php`
- `.env.example`
- `app/Livewire/Pages/Sticker/ProductDesignCard.php`
- `app/Livewire/Pages/Ornament/ProductDesignCard.php`
- `app/Livewire/Modals/Image/ReviewImage.php`
- `app/Livewire/Pages/Marketplace/ListingMetadataStatus.php`
- `tests/Unit/TelegramErrorReporterTest.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them `TelegramErrorReporter` gui text ve Telegram `sendMessage`.
- Them config/env: `TELEGRAM_ERROR_LOG_ENABLED`, `TELEGRAM_ERROR_LOG_BOT_TOKEN`, `TELEGRAM_ERROR_LOG_CHAT_ID`, `TELEGRAM_ERROR_LOG_TIMEOUT`.
- Global exception handler trong `bootstrap/app.php` se report loi chua catch.
- Cac Livewire action quan trong da catch loi van report Telegram bang trait `ReportsUserActionErrors`.
- Context gui gom env/time/user/request/action/component/asset_id/input da loc field nhay cam.

**Loi da gap va cach xu ly:**  
- `php -l` canh bao `use Throwable` trong file khong namespace; da doi thanh `\Throwable`.
- Telegram reporter bat loi rieng va chi log warning, khong lam request user fail them neu bot/config loi.

**Logic can nho:**  
- Cac field nhay cam bi loai khoi request input: password, token, access/refresh token, private_key, credentials_json, vertexJson, marketplaceVertexJson.
- Message gioi han 3900 ky tu de khong vuot Telegram limit.
- Muon bat that can set `.env`, chay `php artisan optimize:clear`, restart server/worker.

**Viec can lam tiep:**  
- Dien bot token/chat id that vao `.env` tren VPS va test bang mot loi Vertex/PSD co chu dich.
- Test da chay: `php -l` cac file sua, `php artisan test tests/Unit/TelegramErrorReporterTest.php tests/Unit/VertexImageGeneratorTest.php`, `php artisan test`, `php artisan optimize:clear`.

### 2026-06-12 15:05:59 +07:00

**Muc tieu:**  
Bat cau hinh Telegram error log bang bot token/chat id user cung cap.

**File da sua/tao:**  
- `.env`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them `TELEGRAM_ERROR_LOG_ENABLED=true`.
- Them `TELEGRAM_ERROR_LOG_BOT_TOKEN`, `TELEGRAM_ERROR_LOG_CHAT_ID`, `TELEGRAM_ERROR_LOG_TIMEOUT` vao `.env`.
- Chay `php artisan optimize:clear`.

**Loi da gap va cach xu ly:**  
- Gui test qua `TelegramErrorReporter` bi Telegram tra `400 Bad Request: chat not found`.
- Nguyen nhan thuong gap: user chua mo chat voi bot/chua bam `/start`, hoac chat id khong dung.

**Logic can nho:**  
- Khong ghi token Telegram vao memory/final answer.
- Sau khi user bam `/start` voi bot, gui test lai bang reporter.

**Viec can lam tiep:**  
- User can bam `/start` trong chat voi bot Telegram, sau do test lai.

### 2026-06-12 15:55:27 +07:00

**Muc tieu:**  
Doi format loi Telegram cho de doc, giong card thong bao co tieu de va block chi tiet.

**File da sua/tao:**  
- `app/Services/Monitoring/TelegramErrorReporter.php`
- `tests/Unit/TelegramErrorReporterTest.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Telegram message dung `parse_mode=HTML`.
- Noi dung tach thanh cac dong: title, time, env, user, action, component, route, URL, IP, error, message.
- Context/request/file dua vao block `<pre>` de de copy/doc.

**Loi da gap va cach xu ly:**  
- Test fail vi JSON trong `<pre>` da escape quote thanh `&quot;`; da cap nhat assertion.

**Logic can nho:**  
- Van cat message 3900 ky tu.
- Van escape HTML truoc khi gui de tranh loi parse mode va khong lam lo markup.
- Da gui test that qua Telegram reporter voi action `sticker.generate_redesign`; khong co warning Telegram moi.

**Viec can lam tiep:**  
- Neu user muon dep hon nua co the them emoji theo tung loai action/status.
- Test da chay: `php -l app/Services/Monitoring/TelegramErrorReporter.php`, `php artisan test tests/Unit/TelegramErrorReporterTest.php`, `php artisan test`.

### 2026-07-04

**Muc tieu:**  
Them nut xoa nhan vien ngay trong modal sua thong tin Wali, chi xoa du lieu o ky luong dang mo.

**File da sua/tao:**  
- `resources/views/livewire/modals/salary/edit-employee-salary.blade.php`
- `app/Livewire/Modals/Salary/EditEmployeeSalary.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Viet lai Blade modal edit salary dung mot root element duy nhat de tranh loi Livewire root tag/multiple root.
- Dua nut `Delete` len ngay canh tieu de `Sua thong tin ... - mm/yyyy`.
- Them modal xac nhan rieng: hien ten user dang thao tac va ten nhan vien; `No` chi dong confirm, `Yes` xoa duy nhat dong luong cua ky dang mo.
- Sau khi xoa: dong confirm, dong modal edit, dispatch refresh Wali va redirect ve page hien tai de reload sach du lieu.
- Clear lai compiled views sau khi sua Blade.

**Loi da gap va cach xu ly:**  
- Confirm modal truoc do bi chen nham vao giua phan header, lam vo cau truc HTML cua component.
- Mot lan ghi file bang PowerShell lam sinh BOM dau file PHP, gay loi `Namespace declaration statement has to be the very first statement`; da ghi lai UTF-8 khong BOM.

**Logic can nho:**  
- Xoa nhan vien trong modal edit chi dong vao `data_salary_zhuzhu` theo `user_id + employee_id + salary_month`; khong xoa nhan vien goc va khong anh huong cac ky khac.
- Full-page va modal Livewire phai giu dung 1 root element.

**Viec can lam tiep:**  
- User test lai nut `Delete` trong modal sua thong tin Wali tren ky luong co du lieu.
- Neu can, co the bo sung spinner to hon cho nut `Yes/Delete` de feedback ro hon.

### 2026-07-04

**Muc tieu:**  
Thu gon modal `Tong ket thang` va `Sua thong tin` cua Wali de giam keo ngang.

**File da sua/tao:**  
- `resources/views/livewire/modals/salary/month-summary.blade.php`
- `resources/views/livewire/modals/salary/edit-employee-salary.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi bang trong 2 modal sang `table-fixed` va giam kich thuoc chu/xuong dong de cot deu hon.
- Doi cac input tien/diem/ngay nghi sang `w-full` trong o hien tai thay vi dat width rem co dinh, giup modal tu can doi theo be rong bang.
- Thu gon `padding` va o `note` de giam keo ngang nhung van giu du so de nhap.
- Clear `view:clear` sau khi sua Blade.

**Logic can nho:**  
- Huong uu tien la `vua du de nhin`, khong mo rong modal qua muc gay roi layout; neu user can co the tang rieng tung cot sau.

**Viec can lam tiep:**  
- User refresh trang Wali va test lai 2 modal tren man hinh that; neu van con 1-2 cot bi chat qua thi chinh tiep theo cot cu the.

### 2026-07-04

**Muc tieu:**  
Dua modal xac nhan xoa nhan vien Wali len tren cung, khong bi che boi modal edit/table.

**File da sua/tao:**  
- `resources/views/livewire/modals/salary/edit-employee-salary.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Tang z-index modal edit len `z-[140]/z-[141]` va modal confirm xoa len `z-[260]/z-[261]`.
- Doi backdrop confirm xoa sang nen toi hon kem `backdrop-blur-sm` de tach ro khoi modal edit dang nam ben duoi.
- Clear compiled views sau khi sua Blade.

**Logic can nho:**  
- Modal xac nhan xoa la lop tren cung trong Wali edit flow; backdrop cua no phai che ca modal edit de user chi thao tac No/Yes.

**Viec can lam tiep:**  
- User refresh Wali va bam Delete de kiem tra confirm khong con bi sticky header/table che.

### 2026-07-04

**Muc tieu:**  
Sua Wali de xoa nhan vien khoi ky luong la mat luon trong danh sach ky do, khong tu dong hien lai vi list active.

**File da sua/tao:**  
- `app/Livewire/Pages/Salary/Wali.php`
- `app/Livewire/Modals/Salary/MonthSummary.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Bo logic tu dong chen nhan vien active vao `rowsForMonth()` cua Wali, nen danh sach luong chi hien cac dong co that trong ky dang chon.
- Cap nhat `MonthSummary` de chi lay du lieu da co trong `data_salary_zhuzhu` cua ky luong, khong pull lai toan bo nhan vien active.
- Giup nut xoa trong modal edit xoa xong la nhan vien bien mat khoi ky hien tai ngay khi tai lai.

**Loi da gap va cach xu ly:**  
- Ban dau `MonthSummary` van bi fallback sang danh sach active nen user xoa xong thay 3 nhan vien van con 3.
- Da doi sang luong du lieu theo `salaryRows` cua ky hien tai, khong tao row gia.

**Logic can nho:**  
- `CreatePeriod`/`AddEmployee` van co the dung nhan vien active de tao ky moi; nhung view hien tai cua mot ky chi duoc render tu du lieu cua ky do.
- Xoa 1 nhan vien chi tac dong ky dang mo, khong anh huong ky khac.

**Viec can lam tiep:**  
- User refresh Wali, mo lai ky luong va bam xoa 1 nhan vien de kiem tra so dong giam dung 1.

### 2026-07-04

**Muc tieu:**  
Fix truong hop nut Delete nhan vien `xlap` xoa xong van hien lai trong ky luong hien tai.

**File da sua/tao:**  
- `app/Livewire/Pages/Salary/Wali.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Phat hien `exportRowsForMonth()` trong Wali van con logic auto-chen toan bo nhan vien active vao ky dang xem, nen sau khi xoa row luong thi nhan vien van duoc render lai nhu row rong.
- Da bo han fallback nay de table chi hien cac dong salary ton tai that trong `data_salary_zhuzhu` cua ky duoc chon.
- Lint lai PHP va clear Blade cache.

**Loi da gap va cach xu ly:**  
- Luc dau nghi la do query delete; kiem tra lai moi thay file `Wali.php` van con block active fallback do lan sua truoc chua an het.
- Da regex-rewrite block do cho dung logic period-only.

**Logic can nho:**  
- Delete trong edit modal chi xoa row salary theo ky; view cung phai render tu row salary cua ky do thi moi thay mat dung.

**Viec can lam tiep:**  
- User refresh man hinh va xoa lai nhan vien `xlap`; neu van con thi can inspect truc tiep DB row cua `xlap` o ky dang test.

### 2026-07-04

**Muc tieu:**  
Thay dashboard tinh bang dashboard Livewire co thong ke theo role/product/thang cho admin, manager va user.

**File da sua/tao:**  
- `app/Services/Dashboard/DashboardStatsService.php`
- `app/Livewire/Pages/Dashboard/Index.php`
- `resources/views/livewire/pages/dashboard/index.blade.php`
- `routes/web.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi route `dashboard` sang Livewire page `App\Livewire\Pages\Dashboard\Index`.
- Them `DashboardStatsService` tong hop du lieu tu `product_design_assets` theo bo loc thang, user, product.
- Admin/manager co filter user, thay card tong so user, tong project/page, tong file, tong approved.
- User thuong khong co card user, chi thay cac product duoc phan quyen va thong ke cua chinh minh.
- Them dashboard UI moi: card overview, bieu do cot 12 thang, top user, va card tung product (users/files/approved/uploaded).
- Product duoc sap thu tu theo `ProductRegistry`, khong phu thuoc cot `sort_order` trong DB.

**Logic can nho:**  
- Nguon thong ke chinh la `product_design_assets`.
- `Files` = so row tao trong thang; `Approved` = `is_approved = true`; `Uploaded` = `drive_uploaded_at` khac null.
- Admin/manager mac dinh xem toan bo user; chi loc theo 1 user khi user do duoc chon.

**Deploy impact:**  
- Can clear route/view/config cache sau khi deploy vi route dashboard da doi tu Blade tinh sang Livewire.

**Queue impact:**  
- Khong co.

**Viec can lam tiep:**  
- User mo dashboard that de test UI/du lieu va neu can co the bo sung chart line/tooltip sau.

### 2026-07-04

**Muc tieu:**  
Fix ParseError khi mo dashboard moi.

**File da sua/tao:**  
- `resources/views/livewire/pages/dashboard/index.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Thay block bieu do dang dung `@forelse/@empty` bang `@if/@foreach` de tranh loi parse Blade trong block chart.
- Doi tinh `maxValue` sang callback thuong thay vi arrow function trong `@php(...)` inline cho on dinh hon tren Blade compiler hien tai.
- Clear `view:clear` sau khi sua.

**Loi da gap va cach xu ly:**  
- Blade bao `unexpected token endforeach, expecting elseif or else or endif` tai block chart cua dashboard.
- Nguyen nhan la parser khong an toan voi cau truc `@forelse` + inline `@php(...)` o block nay.

**Viec can lam tiep:**  
- User refresh dashboard va test render lai. Neu con loi tiep theo thi inspect tiep block Blade khac.

### 2026-07-04

**Muc tieu:**  
Fix them ParseError tiep theo trong dashboard moi o block product cards.

**File da sua/tao:**  
- `resources/views/livewire/pages/dashboard/index.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Thay block product cards dang dung `@forelse/@empty` bang `@if(empty())/@foreach`.
- Tiep tuc giu dashboard Blade o cau truc an toan hon cho Blade compiler hien tai.
- Clear compiled views sau khi sua.

**Logic can nho:**  
- Dashboard Blade nay nen uu tien `@if + @foreach` thay vi `@forelse` de tranh ParseError lan lap tren moi truong hien tai.

**Viec can lam tiep:**  
- User refresh dashboard de test lai render toan bo trang.

### 2026-07-04

**Muc tieu:**  
Fix dut diem ParseError lap lai tren dashboard moi.

**File da sua/tao:**  
- `resources/views/livewire/pages/dashboard/index.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Viet lai toan bo dashboard Blade view bang cau truc PHP control structure (`<?php if ... ?>`, `<?php foreach ... ?>`) thay cho nhieu directive Blade long nhau.
- Muc tieu la tranh triệt để loi parser `expecting elseif/else/endif` dang lap lai tren moi truong hien tai.
- Chay `php artisan view:clear` va `php artisan view:cache` de xac nhan compile pass.

**Loi da gap va cach xu ly:**  
- Sau khi sua tung block `@forelse`, Blade van tiep tuc parse loi o cac block khac trong cung file.
- Giai phap cuoi cung la don gian hoa parser surface bang view PHP-style, compile Blade pass thanh cong.

**Logic can nho:**  
- Dashboard view nay uu tien tinh on dinh parser hon la dung qua nhieu directive Blade nang.

**Viec can lam tiep:**  
- User refresh dashboard va test giao dien/du lieu that.

### 2026-07-04

**Muc tieu:**  
Lam dashboard gon hon va dung huong quan ly hon: user/product/month phu thuoc bo loc, card trung tam la chua duyet/da duyet.

**File da sua/tao:**  
- `app/Services/Dashboard/DashboardStatsService.php`
- `resources/views/livewire/pages/dashboard/index.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Khi da chon user thi `visibleProducts` chi lay product user do duoc phan quyen.
- Thang mac dinh duoc lay theo du lieu thuc te cua user/product da chon thay vi mo rong linh tinh.
- Card tong quan doi thanh `Chua duyet`, `Da duyet`, `Tong file`, va `Users` cho admin/manager.
- Bieu do chinh doi sang trend `Chua duyet` vs `Da duyet` cho de doc va dung nhu dashboard quan ly.
- Card product doi sang `Users / Tong file / Chua duyet / Da duyet` de giam mo ho.

**Logic can nho:**  
- Dashboard khong can so `Files` chung chung nua; gia tri quan trong hon la trang thai duyet.
- Admin/manager co the loc theo user va product; user thuong chi thay scope cua minh.

**Deploy impact:**  
- Blade cache phai clear/cache lai khi deploy dashboard.

**Queue impact:**  
- Khong co.

**Viec can lam tiep:**  
- User refresh dashboard va neu muon co the tiep tuc nang cap sang chart line/tooltip dep hon.

### 2026-07-04

**Muc tieu:**  
Danh rieng layout dashboard: user thuong xem Tien do theo thang full width, admin/manager moi xem layout chia doi va top user.

**File da sua/tao:**  
- `resources/views/livewire/pages/dashboard/index.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Neu khong phai admin/manager thi block `Tien do theo thang` duoc render full width, khong con cot `Top user` ben canh.
- Neu la admin/manager thi dashboard van giu layout chia doi, de xem top user song song.
- Giup user thuong tap trung vao trend cua chinh minh, con admin/manager co goc nhin bao quat hon.
- Clear va cache lai Blade templates sau khi sua.

**Logic can nho:**  
- User thuong = full chart, minimal UI.
- Admin/manager = split chart + top user.

**Viec can lam tiep:**  
- User refresh dashboard va kiem tra giao dien theo role.

### 2026-07-04

**Muc tieu:**  
Chi hien nhom page/product trong dashboard product filter, khong lan sang catalog/idea.

**File da sua/tao:**  
- `app/Services/Dashboard/DashboardStatsService.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Gioi han `visibleProducts` theo danh sach page group: `sticker`, `ornament`, `ornament-etsy`, `ornament-amazon-2`, `proxy`.
- Khi user da duoc chon, dashboard chi loc product trong nhom page cua user do.
- Admin/manager cung chi thay nhom page trong dashboard, khong lay catalog/idea.

**Logic can nho:**  
- Dashboard product filter = group page, giong sidebar, de tranh roi voi catalog/idea.

**Deploy impact:**  
- Can clear cache sau deploy neu dashboard dang cache du lieu cu.

**Viec can lam tiep:**  
- User refresh dashboard va kiem tra dropdown Product chi con nhom page.

### 2026-07-04

**Muc tieu:**  
Top user tren dashboard khong bi co scope theo user dang chon; chi loc theo thang va product.

**File da sua/tao:**  
- `app/Services/Dashboard/DashboardStatsService.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Bo filter `ownerId` khoi `topUsers()`.
- Top user gio luon la bang xep hang chung cua cac user trong thang dang chon.
- Neu co chon product thi top user duoc loc theo product do.
- Chon user chi anh huong card/charts/overview cua scope chinh, khong anh huong bang xep hang top user.

**Logic can nho:**  
- `Top user` = ranking chung theo `month + optional product`.
- `Selected user` = scope chi tiet dashboard, khong phai scope cua ranking.

**Viec can lam tiep:**  
- User refresh dashboard va test lai truong hop chon user Linh + product Sticker.

### 2026-07-04

**Muc tieu:**  
Fix loi dashboard `Undefined variable $ownerId` sau khi doi Top user thanh ranking chung.

**File da sua/tao:**  
- `app/Services/Dashboard/DashboardStatsService.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Xoa dong query con sot `->when($ownerId...)` trong `topUsers()`.
- Doi call `topUsers()` ve dung 2 tham so: thang va product.
- Chay `php -l` va `php artisan optimize:clear`.

**Logic can nho:**  
- `Top user` khong duoc dung selected user/ownerId nua; chi loc theo thang va product.

**Viec can lam tiep:**  
- User refresh dashboard va test lai chon Linh + Sticker.

### 2026-07-04

**Muc tieu:**  
Fix dut diem loi dashboard 500 `Undefined variable $ownerId` va kiem tra khong con loi runtime co ban.

**File da sua/tao:**  
- `app/Services/Dashboard/DashboardStatsService.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi call `topUsers()` chi con truyen thang va product.
- Dam bao `topUsers()` khong loc theo selected user nua, dung logic ranking chung theo thang/product.
- Chay `php -l`, `php artisan view:cache`, `php artisan optimize:clear` va smoke test build dashboard.

**Root cause:**  
- Sau khi doi logic Top user thanh bang xep hang chung, code van con truyen/tham chieu `$ownerId` trong `topUsers()`, gay 500 khi vao `/dashboard`.

**Deploy impact:**  
- Khong doi database, khong anh huong queue. Can deploy code moi va clear/cache lai view.

**Queue impact:**  
- Khong co.

**Viec can lam tiep:**  
- User refresh `/dashboard`, test lai chon Linh + Sticker + thang 06/2026.

### 2026-07-04

**Mục tiêu:**  
Kiểm tra vì sao Linh có 261 sản phẩm nhưng dashboard không hiện như mong đợi, đồng thời sửa lỗi chữ tiếng Việt/biến bị thay nhầm.

**File đã sửa/tạo:**  
- `app/Services/Dashboard/DashboardStatsService.php`
- `resources/views/livewire/pages/dashboard/index.blade.php`
- `AI_MEMORY.md`

**Thay đổi chính:**  
- Xác nhận Linh có 261 dòng `product_design_assets` ở tháng `07/2026`, còn tháng `06/2026` là 0.
- Viết lại sạch `DashboardStatsService.php` sau khi thay chữ quá rộng làm hỏng tên class/biến.
- Sửa view dashboard về đúng biến `availableUsers`, `visibleProducts`, `selectedProductSlug`, `topUsers`.
- Chuyển các nhãn chính trên dashboard sang tiếng Việt có dấu.

**Root cause:**  
- Số 261 không mất; dashboard sẽ hiện khi chọn tháng `07/2026` + user Linh + Sticker.
- Một lệnh thay chữ trước đó thay cả tên biến/class trong code, gây lỗi cú pháp và sai biến view.

**Deploy impact:**  
- Không đổi database. Cần deploy code mới và clear/cache view.

**Queue impact:**  
- Không có.

**Việc cần làm tiếp:**  
- Refresh dashboard, chọn Linh + Sticker + tháng 07/2026 để thấy tổng 261.


### 2026-07-04

**M?c ti?u:**  
T?ch l?i ng?n ng? dashboard: backend/code ti?ng Anh, frontend ti?ng Vi?t c? d?u.

**File ?? s?a/t?o:**  
- `app/Services/Dashboard/DashboardStatsService.php`
- `resources/views/livewire/pages/dashboard/index.blade.php`
- `AI_MEMORY.md`

**Thay ??i ch?nh:**  
- ??a to?n b? label/note ? service v? ti?ng Anh ?? tr?nh l?i m? h?a v? d? b?o tr?.
- Gi? chu?i hi?n th? ? view b?ng ti?ng Vi?t c? d?u cho user.
- D?n l?i c?c bi?n Blade b? ??i nh?m t?n v? ??ng `selectedProductSlug`, `visibleProducts`, `topUsers`.

**Root cause:**  
- L?c tr??c thay ch? qu? r?ng khi?n t?n bi?n v? chu?i trong service/view b? m?o m? h?a.

**Deploy impact:**  
- Ch? c?n deploy code v? clear/cache l?i view.

**Queue impact:**  
- Kh?ng c?.

**Viec can lam tiep:**  
- N?u c?n ch? n?o hi?n ti?ng Vi?t sai trong code BE th? ch? s?a text hi?n th? ? FE, kh?ng ??ng t?n h?m/bi?n n?a.

### 2026-07-06

**Muc tieu:**  
Them cach xac nhan proxy da biet thay doi de tra hang ve mau xanh.

**File da sua/tao:**  
- `app/Livewire/Modals/Proxy/EditProxyItem.php`
- `resources/views/livewire/modals/proxy/edit-proxy-item.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them action admin-only `resetChangedAt()` trong modal edit proxy.
- Nut `Da biet, reset ve xanh` xoa `changed_at` cua proxy item hien tai.
- Giu nguyen `public_ip_change` de con lich su IP da doi, chi xoa moc canh bao hien tai.
- Dispatch `proxy-item-updated` va toast thanh cong sau khi reset.

**Root cause:**  
- Row proxy dang bi do dua tren `changed_at`, nhung admin chua co cach danh dau da xac nhan thay doi.

**Deploy impact:**  
- Khong doi database. Can deploy code va clear view cache.

**Queue impact:**  
- Khong co.

**Viec can lam tiep:**  
- User mo proxy item bi do, bam `Da biet, reset ve xanh` de xoa `Changed At`.

### 2026-07-06

**Muc tieu:**  
Chi hien nut reset proxy ve xanh khi dong proxy dang co `Changed At`.

**File da sua/tao:**  
- `app/Livewire/Modals/Proxy/EditProxyItem.php`
- `resources/views/livewire/modals/proxy/edit-proxy-item.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them state `hasChangedAt` khi mo modal edit proxy.
- Nut `Da biet, reset ve xanh` chi render khi user la admin va proxy item co `changed_at`.
- Neu proxy khong co `Changed At`, modal chi hien nut Cancel/Save.

**Root cause:**  
- Nut reset hien ca khi dong proxy khong co thay doi, gay thua thao tac.

**Deploy impact:**  
- Khong doi database. Da clear compiled views.

**Queue impact:**  
- Khong co.

### 2026-07-08

**Muc tieu:**  
Fix Sticker add bang Ctrl+V/upload tao item nhung anh khong luu duoc va preview bi fallback.

**File da sua/tao:**  
- `resources/views/livewire/modals/sticker/add-product-design.blade.php`
- `app/Livewire/Modals/Sticker/AddProductDesign.php`
- `resources/views/components/image-preview.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi logic Ctrl+V/drop trong modal Sticker sang `$wire.upload('imageUpload', file, ...)` giong flow modal dang hoat dong, thay vi gan `input.files` thu cong.
- Them trang thai `isUploadingImage` de user thay dang upload anh.
- Backend doi sang `store(..., 'public')` va kiem tra `Storage::disk('public')->exists($path)` truoc khi tao item.
- Neu file paste/upload khong luu that, dung lai voi loi `Khong luu duoc file anh...` de tranh tao row co `image_link` tro toi file mat.
- Shared `x-image-preview` reset `failed` khi `src` doi va khi anh load thanh cong.

**Root cause:**  
- Sticker modal cu tu gan file vao input nen Livewire co the khong upload/persist file that, tao DB row nhung file trong `storage/app/public` va `public/storage` khong ton tai.
- Preview component co the giu stale `failed=true` khi doi item/src.

**Deploy impact:**  
- Khong doi database. Can deploy code va clear compiled views.

**Queue impact:**  
- Khong co.

**Viec can lam tiep:**  
- User test lai Ctrl+V anh trong Sticker add modal. Cac row cu co file mat can upload/add lai vi file goc da khong ton tai tren ca `xlap.tech` va `xlap.com.vn`.

### 2026-07-09

**Muc tieu:**  
Fix truong hop user role Manager nhung ngoai bang admin van hien nhu admin.

**File da sua/tao:**  
- `app/Services/User/UserAccessService.php`
- `app/Actions/CreateUserWithProductAccess.php`
- `app/Livewire/Modals/Admin/AddUser.php`
- `app/Livewire/Modals/Admin/EditUser.php`
- `resources/views/livewire/pages/admin/list-user.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Chot `role` la nguon su that de xac dinh admin, khong giu `is_admin=true` cho manager/user nua.
- Khi create/update user, `is_admin` gio chi bang `true` neu `role === admin`.
- Badge trong bang user admin doi sang check theo `role === admin` thay vi `is_admin` de tranh hien sai.

**Root cause:**  
- User cu co the con `is_admin = 1` tu logic cu, du da doi role sang `manager`, nen bang admin van hien chu `a` va bi hieu la admin.

**Deploy impact:**  
- Khong doi database. Can deploy code va clear compiled views.

**Queue impact:**  
- Khong co.

**Viec can lam tiep:**  
- Neu co user da luu sai truoc day, chi can mo edit user va save lai la `is_admin` se duoc dong bo theo `role` moi.
- Graphiti memory turn nay bi 429 quota, da ghi vao `AI_MEMORY.md` local.


### 2026-07-09

**Muc tieu:**  
Fix dropdown loc nhan vien trong Wali bi cat mat goc sau khi mo/chon lai.

**File da sua/tao:**  
- `resources/views/livewire/pages/salary/wali.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Bo `overflow-hidden` khoi wrapper cua panel `Danh sach luong` de dropdown filter khong bi parent cat clip.
- Giu dropdown loc nhan vien cung hang voi tieu de danh sach luong.
- Sua mot so text giao dien Wali bi loi ma hoa sang tieng Viet co dau dung.

**Root cause:**  
- Dropdown duoc dat `absolute` ben trong panel co `overflow-hidden`, nen phan noi ra ngoai panel bi cat mat goc.

**Deploy impact:**  
- Khong doi database. Da chay `php artisan view:clear` va `php artisan view:cache`.

**Queue impact:**  
- Khong co.

**Viec can lam tiep:**  
- User reload trang Wali va bam `Loc nhan vien` de kiem tra dropdown khong con bi che/cat.


### 2026-07-09

**Muc tieu:**  
Fix filter nhan vien Wali bi chi giu 1 checkbox va mat tick khi chon nhieu nhan vien.

**File da sua/tao:**  
- `app/Livewire/Pages/Salary/Wali.php`
- `resources/views/livewire/pages/salary/wali.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Bo hook `updatedSelectedEmployeeIds()` de Livewire tu quan ly mang checkbox multi-select.
- Chuyen sanitize `selectedEmployeeIds` sang luc ap dung filter de tranh ghi de state trong luc user dang tick.
- Doi checkbox tu `wire:model.live` sang `wire:model` de giam re-render nong gay roi tick.

**Root cause:**  
- Hook cap nhat state moi lan tick cung voi `wire:model.live` lam mang checkbox bi ghi de va de gay hien tuong chi con 1 nhan vien duoc chon.

**Deploy impact:**  
- Khong doi database. Da clear va cache lai Blade view.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Them trang `Camp` moi de nhap du lieu campaign theo kieu sheet va tu dong them dong moi o cuoi.

**File da sua/tao:**  
- `app/Models/CampRow.php`
- `app/Livewire/Pages/Camp/Index.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `database/migrations/2026_07_09_000100_create_data_camp_rows_table.php`
- `app/Support/ProductRegistry.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Tao bang `data_camp_rows` co scope theo `user_id` de luu tung dong campaign.
- Them product/page `camp` vao `ProductRegistry` de route/middleware product hoat dong giong cac page khac.
- Dung Livewire page `Camp\Index` hien bang nhap lieu theo cot: Campaign Name, Keyword, Bid, SKU target, ID portfolio, Campaign Daily Budget, Start Date.
- Moi o duoc auto-save khi sua, va neu dong cuoi co du lieu thi tu dong them 1 dong trong moi o duoi.
- Da chay migrate tao bang va seed product `camp` active cho admin.

**Root cause:**  
- User can mot page nhap campaign dang bang sheet de xu ly tiep, nhung hien tai project chua co module `camp`.

**Deploy impact:**  
- Da chay `php artisan migrate --no-ansi` va `php artisan view:clear --no-ansi`.

**Queue impact:**  
- Khong co.

**Viec can lam tiep:**  
- Neu can, co the them xoa dong, copy/paste nhieu dong, export Excel, hoac bo sung cac cot khac sau.

### 2026-07-09

**Muc tieu:**  
Fix sidebar khong hien `Camp` du da cap quyen product cho user.

**File da sua/tao:**  
- `resources/views/livewire/layout/navigation.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them slug `camp` vao filter nhom `PAGE` trong sidebar navigation.
- Da clear compiled views sau khi sua.

**Root cause:**  
- Sidebar `PAGE` dang hardcode danh sach slug va chua include `camp`, nen product co quyen van khong hien o menu.

**Deploy impact:**  
- Khong doi database. Can deploy code va clear view cache.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Bo sung validation va quan ly dong cho trang `Camp`.

**File da sua/tao:**  
- `app/Livewire/Pages/Camp/Index.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- `Bid` chi nhan so, chap nhan so thap phan nhu `0.1`; neu co chu/format sai thi vien o do va hien loi `Chi nhap so, sai dinh dang`.
- `Campaign Daily Budget` chi nhan so nguyen duong lon hon 0, tu `1` tro len.
- `Start Date` chi cho chon tu ngay hien tai tro ve sau bang `min=today` va validate backend `after_or_equal`.
- Them cot `-` de xoa tung dong, co modal confirm Yes moi xoa.
- Them nut `Clear all`, co modal confirm roi moi xoa toan bo du lieu Camp cua user hien tai.
- Du lieu Camp van scope theo `user_id`, moi user chi doc/ghi du lieu cua minh va reload/hom sau van con neu khong xoa.

**Root cause:**  
- Trang Camp moi can rang buoc du lieu va thao tac xoa an toan truoc khi xu ly tiep theo.

**Deploy impact:**  
- Khong doi database. Da clear compiled views.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Tach trang `Camp` thanh 2 tab rieng: `Camp Keyword` va `Camp Auto`.

**File da sua/tao:**  
- `app/Models/CampRow.php`
- `app/Livewire/Pages/Camp/Index.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `database/migrations/2026_07_09_000100_create_data_camp_rows_table.php`
- `database/migrations/2026_07_09_000200_add_camp_type_to_data_camp_rows_table.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them cot `camp_type` vao `data_camp_rows`, default `keyword` cho du lieu cu.
- Them tab `Camp Keyword` va `Camp Auto` tren cung mot page.
- Query/save/delete/clear all deu scope theo `user_id` va `camp_type` hien tai.
- `Camp Keyword` giu du lieu n�y gi?; `Camp Auto` la dataset rieng doc lap.

**Root cause:**  
- Trang Camp ban dau chi co mot tap du lieu, trong khi user can 2 khu vuc lam viec rieng tren cung page.

**Deploy impact:**  
- Da chay `php artisan migrate --no-ansi` them `camp_type` va `php artisan view:clear --no-ansi`.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Them `Campaign bidding strategy` va `Match Type` cho ca 2 tab Camp, dong thoi doi layout Camp Auto va bat loi ngay khi nhap sai.

**File da sua/tao:**  
- `app/Models/CampRow.php`
- `app/Livewire/Pages/Camp/Index.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `database/migrations/2026_07_09_000100_create_data_camp_rows_table.php`
- `database/migrations/2026_07_09_000300_add_strategy_and_match_type_to_data_camp_rows_table.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them 2 cot `bidding_strategy` va `match_type` vao DB va UI.
- `Camp Keyword` van co `Campaign Name` + `Keyword`; `Camp Auto` an 2 cot nay, chi giu cac cot con lai.
- Validation doi sang bao loi ngay o tung o, khong bo qua im lang; `Bid`, `Campaign Daily Budget`, `Start Date` se hien loi truoc khi save.
- `Bid` chap nhan so thap phan nhu `0.1`; `Campaign Daily Budget` la so nguyen duong > 0; `Start Date` khong duoc nho hon ngay hien tai.

**Root cause:**  
- User can layout giua Keyword va Auto khac nhau, va muon biet sai du lieu ngay khi nhap thay vi doi den luc luu DB.

**Deploy impact:**  
- Da chay migrate them 2 cot cho database hien tai va clear view cache.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Them dropdown chuan cho `Campaign bidding strategy` / `Match Type` va them import Excel/CSV cho Camp theo tab dang chon.

**File da sua/tao:**  
- `app/Livewire/Pages/Camp/Index.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `resources/views/livewire/modals/camp/import-camp-rows.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi `Campaign bidding strategy` thanh dropdown: `Dynamic bids - up and down`, `Dynamic bids - down only`, `Fixed bids`.
- Doi `Match Type` thanh dropdown: `exact`, `phrase`, `broad`.
- Them modal import `Excel/CSV` cho Camp, import theo `tab` hien tai (`keyword`/`auto`).
- Nut import bi disable neu tab dang co du lieu persisted; chi import khi tab trong hoac sau `Clear all`.
- Neu file co dong sai dinh dang thi bao loi ngay va chan import; neu hop le thi import vao tab hien tai.
- Sau import dispatch `camp-rows-updated` de page refresh lai du lieu.

**Root cause:**  
- User can rang buoc gia tri chon tay de tranh sai du lieu, dong thoi can import file hang loat theo tung tab Camp.

**Deploy impact:**  
- Khong doi schema trong turn nay. Da clear view cache.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Hien preview bang du lieu trong modal import Camp de user check lai truoc khi import.

**File da sua/tao:**  
- `resources/views/livewire/modals/camp/import-camp-rows.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Modal import Camp doi thanh ban rong hon va them bang preview cac dong hop le se import.
- Neu `Camp Keyword` thi show ca `Campaign Name` + `Keyword`; neu `Camp Auto` thi an 2 cot nay.
- Nut `Import` bi disable khi khong co dong hop le hoac con `rowErrors`.

**Root cause:**  
- User can nhin truoc cac dong du lieu hop le se vao DB, khong chi muon thay thong ke tong quan.

**Deploy impact:**  
- Khong doi database. Da clear view cache.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Fix loi export Camp Keyword tra ve sai return type.

**File da sua/tao:**  
- `app/Livewire/Pages/Camp/Index.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi return type cua `exportData()` tu `StreamedResponse` sang `BinaryFileResponse` vi `response()->download()` tra ve `BinaryFileResponse`.
- Chay `php -l` va clear view cache.

**Root cause:**  
- Laravel download response khong phai streamed response, gay TypeError khi Livewire goi export.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Fix import/export `Camp Keyword` de `Portfolio Id` giu dung so day du va `Campaign Daily Budget` khong bi xuat dang thap phan.

**File da sua/tao:**  
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `app/Livewire/Pages/Camp/Index.php`
- `app/Services/Camp/CampKeywordExportService.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them normalize thu cong cho `portfolio_id` dang scientific notation nhu `1.9139E+14` thanh chuoi day du `191390000000000` ngay luc import.
- Khi load/sua row Camp, `portfolio_id` cung duoc normalize lai de du lieu cu dang `E+` hien thi dung.
- Export `Portfolio Id` ra dung chuoi day du, tranh mat so do Excel rut gon.
- Export `Campaign Daily Budget` ra so nguyen duong nhu `3`, khong con `3.00`.
- Fix import `Start Date` parse ve `Y-m-d` truoc khi so sanh, tranh so sai dinh dang `dd/mm/yyyy` voi `Y-m-d`.
- Sua lai overlay spinner export trong Blade do co markup loi `x-data>{`.

**Root cause:**  
- `portfolio_id` dang duoc giu nguyen chuoi Excel rut gon khoa hoc nen UI/export khong ra du so; budget dang lay truc tiep so decimal nen xuat `3.00`; spinner export co markup Blade/Alpine bi loi.

**Deploy impact:**  
- Khong doi database. Da chay `php -l` cho 3 file PHP va `php artisan view:clear --no-ansi`.

**Queue impact:**  
- Khong co.

**Follow-up notes:**  
- Neu DB da co nhieu dong cu dang `E+`, hien tai UI/export da hien dung nhung chua backfill hang loat trong DB. Co the viet lenh normalize du lieu cu neu can.

### 2026-07-09

**Muc tieu:**  
Fix import `Start Date` cho Camp khi file CSV xuat dang ngay mot chu so, va them spinner phu toan modal trong luc xu ly file/import.

**File da sua/tao:**  
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `resources/views/livewire/modals/camp/import-camp-rows.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- `normalizeDate()` cho phep doc them cac dinh dang `j/n/Y`, `m/d/Y`, `n/j/Y` ngoai `Y-m-d` va `d/m/Y`.
- File CSV user dua co `Start Date` dang `7/9/2026`, truoc do khong match regex nen bi bao nhu trong/khong doc duoc.
- Them loading overlay che toan card modal khi `importFile` dang parse hoac `startImport` dang chay; trong luc do modal bi khoa thao tac va chi mo lai khi co ket qua hoac loi.

**Root cause:**  
- Import chi nhan `d/m/Y` hai chu so, trong khi CSV export ra ngay kieu mot chu so `7/9/2026`; UX modal chi doi text o nut chua spin phu het card nen cam giac khong ro dang xu ly.

**Deploy impact:**  
- Khong doi database. Da chay `php -l app/Livewire/Modals/Camp/ImportCampRows.php` va `php artisan view:clear --no-ansi`.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Them spinner truc tiep tren nut `Export data` cua trang Camp.

**File da sua/tao:**  
- `resources/views/livewire/pages/camp/index.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Nut `Export data` dung `wire:loading` va `wire:target="exportData"` de doi text/icon spin ngay khi bam.
- Nut export bi disable trong luc request export dang chay, den khi download response hoac loi tra ve thi Livewire tu dung loading.

**Root cause:**  
- Trang chi co overlay dua vao `$isExporting`, nhung khi download response Livewire co the khong cap nhat UI nhanh nhu `wire:loading`; user can spin ngay tren nut bam.

**Deploy impact:**  
- Khong doi database. Da chay `php artisan view:clear --no-ansi`.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Fix logic them dong moi va xoa dong trong bang Camp.

**File da sua/tao:**  
- `app/Livewire/Pages/Camp/Index.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Chi them dong trong moi o cuoi bang khi dong cuoi da day du tat ca cot bat buoc.
- Khong tao record moi trong DB neu dong dang sua chua du cac truong bat buoc.
- Khi xoa dong, bo cach `array_splice` truc tiep tren state Livewire; doi sang xoa DB xong `loadRows()` lai de index `#` va hang hien thi khong bi lech.

**Root cause:**  
- Logic cu them dong moi ngay khi dong cuoi co bat ky du lieu nao, gay du dong trong; delete truc tiep trong mang state co the lam Livewire giu key cu va hien thi sai thu tu/hang.

**Deploy impact:**  
- Khong doi database. Da chay `php -l app/Livewire/Pages/Camp/Index.php` va `php artisan view:clear --no-ansi`.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Si?t validation Camp cho text co dau, so va dinh dang ngay.

**File da sua/tao:**  
- `app/Livewire/Pages/Camp/Index.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- `campaign_name`, `keyword`, `sku_target`, `portfolio_id` bao loi khi co ky tu co dau; UI hien message `vui l�ng kh�ng nh?p d?u` va vi?n d?.
- `bid` va `campaign_daily_budget` dung regex so, sai dinh dang thi bao `vui l�ng ch? nh?p s?`.
- `start_date` dung `dd/mm/yyyy`, sai thi bao `dd/mm/yyyy`.
- Update error rendering trong table de lay message tu validator thay vi text hardcode cu.

**Root cause:**  
- Validation cu chi bao loi chung chung va chua tach ro text co dau / so / dinh dang ngay theo yeu cau user.

**Deploy impact:**  
- Khong doi database. Da chay `php -l app/Livewire/Pages/Camp/Index.php` va `php artisan view:clear --no-ansi`.

**Queue impact:**  
- Khong co.

### 2026-07-09

**Muc tieu:**  
Bat validation Camp ngay khi user dang nhap/sua du lieu trong o.

**File da sua/tao:**  
- `app/Livewire/Pages/Camp/Index.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Bo `debounce` o binding Camp de UI validate nhanh hon.
- Them hook `updated()` cho toan bo property `rows.*.*` de moi lan edit la chay validateCell ngay lap tuc.
- Giup cac loi `khong nhap dau`, `chi nhap so`, `dd/mm/yyyy` hien ra ngay khi user vua go.

**Root cause:**  
- Binding cu co debounce va chi validate theo luong `updatedRows`, nen cam giac van co do tre khi user sua tung o.

**Deploy impact:**  
- Khong doi database. Da chay `php -l app/Livewire/Pages/Camp/Index.php` va `php artisan view:clear --no-ansi`.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Fix cong thuc Wali tinh tien diem le va ngay cong theo ngay nghi duoc phep.

**File da sua/tao:**  
- `app/Services/Salary/WaliSalaryCalculator.php`
- `app/Livewire/Pages/Salary/Wali.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- `WaliSalaryCalculator` tinh `payroll_score` lam tron 1 chu so thap phan truoc khi tinh tien diem le de khop diem user thay tren UI.
- `standard_work_days` doi sang cong thuc `so ngay trong thang - so ngay duoc nghi`.
- `actual_work_days` tinh bang `standard_work_days - max(0, ngay da nghi - ngay duoc nghi)`.
- Trang Wali khong con dung cong thuc copy rieng nua, ma dung chung `WaliSalaryCalculator` nhu modal tong ket va modal edit.

**Root cause:**  
- Trang Wali co logic decorate/tinh lai rieng, van dung cong thuc cu tru Chu nhat va tru thang ngay nghi vao cong thuc te.
- Diem hien thi tren UI la 1 so thap phan nhung tien diem le co the tinh theo diem noi bo nhieu chu so hon, gay lech voi cach user tinh tay.

**Validation:**  
- `php -l app/Services/Salary/WaliSalaryCalculator.php` pass.
- `php -l app/Livewire/Pages/Salary/Wali.php` pass.
- Test mau Lucie: diem 1820.9, thang 06/2026, nghi 7, duoc nghi 6 => cong chuan 24, cong thuc te 23, tien diem le 1.187.330.
- Da chay `php artisan view:clear` va `php artisan view:cache`.

**Deploy impact:**  
- Khong doi database. Can deploy code moi va reload trang Wali.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Them sap xep thu tu nhan vien Wali va luu lai de lan sau khong phai keo/sap xep lai.

**File da sua/tao:**  
- `database/migrations/2026_07_10_090000_add_sort_order_to_data_salary_zhuzhu_table.php`
- `app/Models/DataSalaryZhuzhu.php`
- `app/Livewire/Modals/Salary/CreatePeriod.php`
- `app/Livewire/Modals/Salary/AddEmployee.php`
- `app/Livewire/Pages/Salary/Wali.php`
- `resources/views/livewire/pages/salary/wali.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them cot `sort_order` vao `data_salary_zhuzhu` va backfill thu tu theo tung user/ky luong.
- Danh sach Wali order theo `sort_order`, sau do moi theo ten nhan vien.
- Tao ky moi copy thu tu tu ky truoc; neu khong co ky truoc thi gan thu tu tu dau danh sach.
- Them nhan vien moi vao ky hien tai thi tu dong nam cuoi danh sach.
- Them cot `Sap xep` tren table voi nut len/xuong, bam la luu ngay vao database.

**Root cause:**  
- Truoc do khong co field luu thu tu rieng theo ky luong nen moi lan load lai se sap xep theo ten.

**Validation:**  
- `php -l` pass cho cac file PHP da sua.
- `php artisan view:clear` va `php artisan view:cache` pass.

**Deploy impact:**  
- Can chay migration `php artisan migrate` tren moi truong deploy.
- Khong co queue impact.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Lam UI sap xep nhan vien Wali gon hon, de nhin hon.

**File da sua/tao:**  
- `resources/views/livewire/pages/salary/wali.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Bo cot rieng `Sap xep` de table khong bi dai them.
- Dua nut len/xuong vao ngay trong cot `Nhan vien`, nam canh ten/avatar.
- Giu `click.stop` de bam sap xep khong mo modal edit.

**Root cause:**  
- Cot sap xep rieng chiem dien tich va lam UI bang luong rong/roi hon.

**Validation:**  
- Da chay `php artisan view:clear` va `php artisan view:cache`.

**Deploy impact:**  
- Khong doi database. Chi doi Blade UI.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Doi UI sap xep Wali sang kieu co icon `☰`, hover moi hien nut len/xuong.

**File da sua/tao:**  
- `resources/views/livewire/pages/salary/wali.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them icon `☰` nho canh ten nhan vien de goi y co the sap xep.
- Nut `↑ ↓` mac dinh an, chi hien khi hover vao khu vuc ten nhan vien.
- Giu nguyen `click.stop` de thao tac sap xep khong mo modal edit.

**Validation:**  
- Da chay `php artisan view:clear` va `php artisan view:cache`.

**Deploy impact:**  
- Khong doi database. Chi doi Blade UI.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Cho phep cam icon `☰` de keo-tha sap xep nhan vien Wali.

**File da sua/tao:**  
- `app/Livewire/Pages/Salary/Wali.php`
- `resources/views/livewire/pages/salary/wali.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them method `reorderEmployee()` de doi thu tu theo thao tac keo-tha va luu lai `sort_order` theo thu tu moi.
- Icon `☰` chuyen thanh handle `draggable=true`.
- Moi dong nhan vien nhan `dragover/drop` va goi Livewire de luu thu tu ngay.
- Giu nut `↑ ↓` lam fallback khi hover.

**Validation:**  
- `php -l app/Livewire/Pages/Salary/Wali.php` pass.
- Da chay `php artisan view:clear` va `php artisan view:cache`.

**Deploy impact:**  
- Khong them package moi, khong doi database ngoai migration `sort_order` da co truoc do.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Tach luong Camp Auto va Camp Keyword trong UI/import, bo `Match Type` khoi Camp Auto.

**File da sua/tao:**  
- `app/Livewire/Pages/Camp/Index.php`
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `resources/views/livewire/modals/camp/import-camp-rows.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Camp Auto khong con hien cot `Match Type` tren table.
- Camp Auto khong validate/khong require/khong luu `match_type`; khi save auto thi `match_type = null`.
- Import Camp Auto khong can cot `Match Type`; Import Camp Keyword van can `Campaign Name`, `Keyword`, `Match Type`.
- Preview import Auto an `Match Type` va modal hien note template rieng cho Auto/Keyword.
- Nut import doi label theo tab: `Import Camp Keyword` hoac `Import Camp Auto`.

**Root cause:**  
- Truoc do Camp Auto va Camp Keyword dang dung chung cot/rule `Match Type`, trong khi user can moi tab la mot template va logic rieng.

**Deploy impact:**  
- Khong doi database. Da chay `php -l` cho `Index.php`, `ImportCampRows.php` va `php artisan view:clear --no-ansi`.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Cap nhat lai cong thuc ngay cong Wali theo rule moi user chot.

**File da sua/tao:**  
- `app/Services/Salary/WaliSalaryCalculator.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Giu `cong_chuan = so_ngay_trong_thang - so_ngay_duoc_nghi`.
- Doi `cong_thuc_te = cong_chuan - so_ngay_xin_nghi`.
- `so_ngay_duoc_nghi` chi de tinh cong chuan, khong bu tru cho `xin_nghi` trong cong thuc te.
- `nghi_vuot` van tinh rieng de hien thi thong tin.

**Validation:**  
- `php -l app/Services/Salary/WaliSalaryCalculator.php` pass.
- Test mau 06/2026: duoc nghi 6, xin nghi 7 => cong chuan 24, cong thuc te 17.
- Da chay `php artisan view:clear` va `php artisan view:cache`.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Doc mau input/output Camp Auto va lam export/import Auto rieng theo template `Auto Campaign.xlsx`.

**File da sua/tao:**  
- `app/Models/CampRow.php`
- `app/Livewire/Pages/Camp/Index.php`
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `app/Services/Camp/CampAutoExportService.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `database/migrations/2026_07_10_140000_add_keyword_negative_to_data_camp_rows_table.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them cot DB `keyword_negative` cho Camp Auto input `Keyword Text Negative`.
- Tab Auto co them cot `Keyword Text Negative`; tab Keyword khong dung cot nay.
- Nut export Camp ho tro ca `keyword` va `auto`; Auto dung service rieng `CampAutoExportService`.
- Import Auto doc template rieng, khong can `Match Type`, co the doc them `Keyword Text Negative`.
- Export Auto duoc map theo mau `Auto Campaign.xlsx`: moi dong input sinh 3 block campaign (`close-match`, `loose-match`, `substitutes`), moi block gom `Campaign`, 2 dong `Bidding Adjustment`, `Ad Group`, `Product Ad`, 4 dong `Product Targeting` (`close-match`, `loose-match`, `complements`, `substitutes`) va 1 dong trong phan cach.

**Root cause:**  
- Camp Auto va Camp Keyword co template output khac nhau hoan toan; logic cu dung chung luong Keyword nen khong the map dung file Auto mau.

**Validation:**  
- `php -l app/Services/Camp/CampAutoExportService.php` pass.
- `php -l app/Livewire/Pages/Camp/Index.php` pass.
- `php -l app/Livewire/Modals/Camp/ImportCampRows.php` pass.
- `php artisan view:clear --no-ansi` pass.
- `php artisan migrate --no-ansi` da chay them cot `keyword_negative`.

**Deploy impact:**  
- Can chay migration `2026_07_10_140000_add_keyword_negative_to_data_camp_rows_table.php` tren moi truong deploy.

**Queue impact:**  
- Khong co.

**Follow-up notes:**  
- Mau output Auto user dua khong co gia tri thuc te cho `Keyword Text Negative` o file dau ra, nen hien tai cot nay moi duoc luu/input; chua map vao block export vi khong co mau xac dinh de noi chinh xac.

### 2026-07-10

**Muc tieu:**  
Fix import Camp Auto de doc dung `Bid` dang dau phay thap phan va uu tien cach hieu ngay theo file Excel mau.

**File da sua/tao:**  
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them `normalizeDecimal()` de `Bid` dang `0,2` duoc hieu thanh `0.2` truoc khi validate va luu DB.
- Doi thu tu parse ngay trong `normalizeDate()` de uu tien `m/d/Y`, `n/j/Y` truoc `d/m/Y`, `j/n/Y`; file mau `9/1/2026` se duoc hieu theo kieu Excel Auto user gui.

**Root cause:**  
- `is_numeric('0,2')` tra ve false nen toan bo dong import Auto bi bao `Bid khong hop le`; ngay dang `9/1/2026` can uu tien parse theo thu tu file mau thay vi logic cu.

**Validation:**  
- `php -l app/Livewire/Modals/Camp/ImportCampRows.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Bo hoan toan cot `Keyword Text Negative` khoi Camp Auto vi user khong can.

**File da sua/tao:**  
- `app/Models/CampRow.php`
- `app/Livewire/Pages/Camp/Index.php`
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `resources/views/livewire/pages/camp/index.blade.php`
- `database/migrations/2026_07_10_140100_drop_keyword_negative_from_data_camp_rows_table.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Xoa `keyword_negative` khoi model fillable, state page, validation, import parser va UI Camp Auto.
- Xoa cot hien thi `Keyword Text Negative` tren tab Auto.
- Tao migration cleanup drop cot `keyword_negative` khoi DB hien tai vi cot da tung duoc migrate vao local.
- Sua nut `Export data` de tab Auto cung bam export duoc, khong con bi khoa boi dieu kien chi cho Keyword.

**Validation:**  
- `php -l app/Models/CampRow.php` pass.
- `php -l app/Livewire/Pages/Camp/Index.php` pass.
- `php -l app/Livewire/Modals/Camp/ImportCampRows.php` pass.
- `php artisan view:clear --no-ansi` pass.
- `php artisan migrate --no-ansi` da drop cot `keyword_negative`.

**Deploy impact:**  
- Can chay migration `2026_07_10_140100_drop_keyword_negative_from_data_camp_rows_table.php` tren moi truong deploy neu da tung co cot `keyword_negative`.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Chan import sai `ID portfolio` khi Excel rut gon khoa hoc mat so chinh xac.

**File da sua/tao:**  
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- `normalizePortfolioId()` cua import Camp nay tra ve `null` khi gap scientific notation qua ngan nhu `2.60912E+14` vi khong the khoi phuc so goc `260911546954776`.
- Import se bao loi ro: `ID portfolio dang bi Excel rut gon, vui long format cot nay thanh Text hoac paste day du so goc.` thay vi tu convert sai thanh `260912000000000`.
- Neu file `.xlsx` con raw value day du hoac scientific du day du chu so co nghia, import van normalize dung.

**Root cause:**  
- Khi Excel/CSV chi con hien thi `2.60912E+14`, cac chu so giua/cuoi da mat nen PHP khong the doan lai chinh xac; can chan de tranh luu sai portfolio id.

**Validation:**  
- `php -l app/Livewire/Modals/Camp/ImportCampRows.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Gan template import rieng cho Camp Keyword / Camp Auto va cho admin thay template moi de de doi file mau.

**File da sua/tao:**  
- `app/Livewire/Modals/Admin/EditImportTemplate.php`
- `app/Livewire/Pages/Admin/ListUser.php`
- `resources/views/livewire/modals/camp/import-camp-rows.blade.php`
- `resources/views/livewire/pages/admin/list-user.blade.php`
- `public/templates/camp-keyword-template.xlsx`
- `public/templates/camp-auto-template.xlsx`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them 2 template key moi trong admin: `camp_keyword` va `camp_auto`.
- Admin Users > Import Templates gio quan ly duoc ca template Camp Keyword va Camp Auto; click vao row de upload file moi.
- File moi duoc copy de len `public/templates/` voi ten co dinh (`camp-keyword-template.xlsx`, `camp-auto-template.xlsx`), nen file cu bi ghi de -> khong phinh dung luong.
- Modal import Camp show link tai template theo tab dang chon; neu chua co file thi bao admin can upload.
- Bootstrap template tu file local dang co: `Keyword Campaign (2).xlsx` va `Auto Campaign.xlsx`.

**Root cause:**  
- Camp can 2 template import rieng cho 2 luong Keyword/Auto, va user muon admin tu thay file mau sau nay ma khong giu nhieu file cu.

**Validation:**  
- `php -l app/Livewire/Modals/Admin/EditImportTemplate.php` pass.
- `php -l app/Livewire/Pages/Admin/ListUser.php` pass.
- `php artisan view:clear --no-ansi` pass.
- Verify co file `public/templates/camp-keyword-template.xlsx` va `public/templates/camp-auto-template.xlsx`.

**Deploy impact:**  
- Khong doi database. Can dam bao thu muc `public/templates/` ghi duoc tren moi truong deploy.

**Queue impact:**  
- Khong co.

**Follow-up notes:**  
- 2 path desktop user dua (`C:\Users\Admin\OneDrive\Desktop\camp-keywrok`, `C:\Users\Admin\OneDrive\Desktop\camp-auto`) khong ton tai trong local run nay, nen da bootstrap tu cac file mau trong Downloads/public templates thay the.

### 2026-07-10

**Muc tieu:**  
Fix loi 500 khi admin upload Camp import template do web server khong co quyen ghi `public/templates`.

**File da sua/tao:**  
- `app/Livewire/Modals/Admin/EditImportTemplate.php`
- `app/Livewire/Pages/Admin/ListUser.php`
- `resources/views/livewire/pages/admin/list-user.blade.php`
- `resources/views/livewire/modals/camp/import-camp-rows.blade.php`
- `storage/app/public/import-templates/*`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi noi luu template admin upload tu `public/templates` sang disk `public` tai `storage/app/public/import-templates` de dung thu muc writable cua Laravel.
- Link tai template doi sang `asset('storage/import-templates/...')`.
- Khi upload file moi, `Storage::disk('public')->put()` ghi de cung filename co dinh, nen file cu bi thay the va khong phinh dung luong.
- Them guard neu luu that bai thi hien validation error thay vi nem 500.
- Bootstrap template hien co tu `public/templates` sang `storage/app/public/import-templates`.

**Root cause:**  
- Production `/www/wwwroot/xlap.tech/public/templates` khong writable boi PHP user, nen `copy()` vao public path bi `Permission denied`.

**Validation:**  
- `php -l app/Livewire/Modals/Admin/EditImportTemplate.php` pass.
- `php -l app/Livewire/Pages/Admin/ListUser.php` pass.
- `php artisan view:clear --no-ansi` pass.
- `php artisan storage:link --no-ansi` bao link da ton tai tai local, chap nhan duoc.

**Deploy impact:**  
- Tren production can dam bao `storage/app/public` writable va `public/storage` symlink ton tai (`php artisan storage:link` neu chua co).

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Fix import Camp `.xlsx` khi file co nhieu sheet va du lieu nam o active sheet khac `sheet1`.

**File da sua/tao:**  
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Parser `.xlsx` khong con co dinh doc `sheet1` nua.
- Nay uu tien sheet dang `activeTab` trong `workbook.xml`; neu sheet active khong co data thi fallback sang sheet dau tien co dong du lieu thuc su.
- Fix case file `camp-auto.xlsx` co `sheet1` trong, du lieu nam o `sheet2`, truoc do gay `No rows found to import.`

**Root cause:**  
- Nhieu file Excel user tao co tab dau trong hoac tab du lieu nam o sheet khac; parser cu chi doc worksheet dau tien nen bo sot toan bo input.

**Validation:**  
- `php -l app/Livewire/Modals/Camp/ImportCampRows.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Fix import Camp Auto khi `Start Date` trong `.xlsx` duoc Excel luu thanh serial number.

**File da sua/tao:**  
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- `normalizeDate()` nay nhan ngay Excel serial nhu `46213` va convert bang `PhpSpreadsheet\Shared\Date::excelToDateTimeObject()` sang `Y-m-d`.
- Them `cleanText()` cho `SKU target` de xoa newline/tab thua trong cell, vi file `camp-auto (1).xlsx` co SKU bi xuong dong truoc `BH1`.

**Root cause:**  
- File Excel co o `Start Date` la number serial (`46213`) chu khong phai chuoi `m/d/Y`/`d/m/Y`, parser cu khong hieu nen bao `Start Date khong duoc trong`.

**Validation:**  
- `php -l app/Livewire/Modals/Camp/ImportCampRows.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Thong nhat dinh dang ngay Camp import/export theo chuan ngay/thang/nam de `10/07/2026` xuat ra `20260710`.

**File da sua/tao:**  
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi thu tu parse ngay trong import Camp: uu tien `d/m/Y`, `j/n/Y` truoc `m/d/Y`, `n/j/Y`.
- Giu export Camp Auto dang `Ymd`, nen DB date `2026-07-10` se xuat thanh `20260710`.

**Root cause:**  
- Chuoi ngay co dau `/` nhu `09/01/2026`/`10/07/2026` truoc do bi uu tien doc theo kieu thang/ngay/nam, lam dau ra bi lech logic ngay thang.

**Validation:**  
- `php -l app/Livewire/Modals/Camp/ImportCampRows.php` pass.
- `php artisan view:clear --no-ansi` pass.
- Check nhanh `10/07/2026` parse thanh `2026-07-10`, export Auto se thanh `20260710`.

**Deploy impact:**  
- Khong doi database. Can deploy code va clear view/cache neu production dang cache.

**Queue impact:**  
- Khong co.

**Follow-up notes:**  
- Neu file Excel luu Start Date bang serial number thi serial `46213` moi la `2026-07-10`; serial `46031` la ngay khac theo Excel 1900 date system, nen can dam bao file nguon that su luu dung ngay.

### 2026-07-10

**Muc tieu:**  
Thong nhat dinh dang ngay cho ca Camp Auto va Camp Keyword.

**File da sua/tao:**  
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `app/Services/Camp/CampKeywordExportService.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Import ca 2 tab Camp nay uu tien parse ngay chuoi theo `dd/mm/yyyy`.
- Export Camp Keyword doi `Start Date` tu `Y-m-d` sang `Ymd` de dong bo voi Camp Auto.
- Ket qua: ngay nhap `10/07/2026` se luu thanh `2026-07-10`, export ra `20260710` cho ca Auto va Keyword.

**Root cause:**  
- Import dang uu tien `m/d/Y`, con export Keyword lai dung format khac Auto, nen 2 tab khong dong bo.

**Validation:**  
- `php -l app/Livewire/Modals/Camp/ImportCampRows.php` pass.
- `php -l app/Services/Camp/CampKeywordExportService.php` pass.
- `php -l app/Services/Camp/CampAutoExportService.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Giam hien tuong chopup/chop chop anh o 4. Person A/B va 6. Mockup cua Ornament Amazon 2.

**File da sua/tao:**  
- `resources/views/livewire/pages/ornament-amazon-two/product-design-card.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Bo `wire:key` phu thuoc vao hash URL/hinh anh o block Person A/B.
- Bo `wire:key` phu thuoc vao hash images/states o block Mockup B5.
- Dung key on dinh theo `asset id`/`person key` de Livewire khong remount ca block moi lan URL anh/state doi.

**Root cause:**  
- `wire:key` dang gan theo `md5(url)` va `md5(images/state)`, nen moi lan cap nhat anh hoac state, Livewire xem nhu node moi va mount lai Alpine/img -> gay nhap nhay, reload anh, cam giac chop chop.

**Validation:**  
- `php -l resources/views/livewire/pages/ornament-amazon-two/product-design-card.blade.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Chot lai cong thuc `Tong luong` Wali theo rule moi nhat cua user.

**File da sua/tao:**  
- `app/Services/Salary/WaliSalaryCalculator.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- `tong_luong = luong_co_ban / cong_chuan * cong_thuc_te + thuong_ngay`.
- `bo_sung` va `tien_khac` khong con nam trong `tong_luong`.
- `thuc_nhan = tong_luong + tien_diem_le + hoa_hong + bo_sung + tien_khac`.

**Validation:**  
- `php -l app/Services/Salary/WaliSalaryCalculator.php` pass.
- Test mau: base 10.000.000, cong chuan 24, cong thuc te 17, thuong ngay 100.000 => tong luong 7.183.333.
- Da chay `php artisan view:clear` va `php artisan view:cache`.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Can bang kich thuoc card 1. Input Image voi 2. Main Image va 3. Script o Ornament Amazon 2.

**File da sua/tao:**  
- `resources/views/livewire/pages/ornament-amazon-two/product-design-card.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Dua `aspect-[4/4.45]` vao wrapper card Input Image, cung cau truc voi Main Image/Script.
- Doi `x-image-preview` ben trong sang `h-full w-full` de anh fill dung khung, giu overlay thong tin san pham trong card.

**Root cause:**  
- Input Image dat ty le/kieu khung o component con thay vi wrapper card, khac cau truc voi Main Image va Script nen height/position hien thi khong dong deu.

**Validation:**  
- `php -l resources/views/livewire/pages/ornament-amazon-two/product-design-card.blade.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Tang toc hien thi anh Drive tren Ornament Amazon 2 card va chi reload card khi workflow Auto dang chay.

**File da sua/tao:**  
- `app/Services/Image/ImageLinkPreviewService.php`
- `resources/views/livewire/pages/ornament-amazon-two/product-design-card.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Preview Google Drive tra truc tiep URL thumbnail `drive.google.com/thumbnail` thay vi di qua signed preview proxy cua app, bo qua mot request server trung gian.
- Card Ornament Amazon 2 chi con `wire:poll.5s` khi `workflow_status` la `running`; khi khong Auto dang chay, card khong tu reload nua.

**Root cause:**  
- Anh Drive truoc do phai qua image preview controller truoc khi browser nhan duoc image, lam tang do tre.
- Card poll moi 0.5 giay ke ca khi idle lam DOM bi morph/reload lap lai, anh lazy preview de cham hien hoac chop.

**Validation:**  
- `php -l app/Services/Image/ImageLinkPreviewService.php` pass.
- `php -l resources/views/livewire/pages/ornament-amazon-two/product-design-card.blade.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database. Trinh duyet can truy cap duoc Google Drive thumbnail cua file da share.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Giam thoi gian load va lag trang Ornament Amazon 2.

**File da sua/tao:**  
- `app/Livewire/Pages/OrnamentAmazonTwo/ProductDesignCard.php`
- `app/Livewire/Pages/OrnamentAmazonTwo/ListOrnamentAmazonTwo.php`
- `resources/views/livewire/pages/ornament-amazon-two/list-ornament-amazon-two.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Card khong con goi `workflowData()` 2 lan trong cung mot render; workflow duoc lay mot lan va truyen sang preview/view.
- Cache ket qua `Schema::hasTable('sub_product_design_assets')` trong request thay vi check lai tren tung card.
- Trang list chi mount panel cua tab dang mo; truoc do ca 3 panel `all/unapproved/approved` deu duoc mount du chi an bang Alpine `x-show`.
- Them `activeStatus` vao Livewire session va dong bo voi localStorage khi user doi tab.

**Root cause:**  
- 3 status panel va nhieu nested card cung render/query dong thoi, trong khi 2 tab an van tai data. Moi card cung lap lai workflow/schema lookup nen tang request/DB work.

**Validation:**  
- `php -l app/Livewire/Pages/OrnamentAmazonTwo/ProductDesignCard.php` pass.
- `php -l app/Livewire/Pages/OrnamentAmazonTwo/ListOrnamentAmazonTwo.php` pass.
- `php -l resources/views/livewire/pages/ornament-amazon-two/list-ornament-amazon-two.blade.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Chot cong thuc `Tong luong` Wali theo ban cuoi cung user vua xac nhan.

**File da sua/tao:**  
- `app/Services/Salary/WaliSalaryCalculator.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- `tong_luong = (luong_co_ban / cong_chuan * cong_thuc_te) + luong_cung_bien_dong`.
- `thuc_nhan = tong_luong + tien_diem_le + hoa_hong + thuong_ngay + bo_sung + tien_khac`.
- Khong dung `thuong_ngay` cho cong thuc `tong_luong` nua.

**Validation:**  
- `php -l app/Services/Salary/WaliSalaryCalculator.php` pass.
- Test mau: base 10.000.000, cong chuan 24, cong thuc te 17, diem 1820.9 => `luong_cung_bien_dong` 1.480.000, `tong_luong` 8.563.333.
- Da chay `php artisan view:clear` va `php artisan view:cache`.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Fix loi Livewire `setActiveStatus` khong tim thay khi doi tab Ornament Amazon 2.

**File da sua/tao:**  
- `app/Livewire/Pages/OrnamentAmazonTwo/ListOrnamentAmazonTwo.php`
- `resources/views/livewire/pages/ornament-amazon-two/list-ornament-amazon-two.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Parent List component lang nghe event `ornament-amazon-two-active-status-changed` bang `#[On(...)]` va xu ly `setActiveStatus()`.
- Alpine doi tab dispatch Livewire event toan cuc thay vi goi `this.$wire.setActiveStatus()`.

**Root cause:**  
- Trong DOM nested Livewire, `$wire` tu Alpine duoc resolve thanh StatusPanel con, component nay khong co public method `setActiveStatus`, gay HTTP 500.

**Validation:**  
- `php -l app/Livewire/Pages/OrnamentAmazonTwo/ListOrnamentAmazonTwo.php` pass.
- `php -l resources/views/livewire/pages/ornament-amazon-two/list-ornament-amazon-two.blade.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Them fallback de tab Ornament Amazon 2 chuyen muot va khong 500 neu request va nham vao StatusPanel con.

**File da sua/tao:**  
- `app/Livewire/Pages/OrnamentAmazonTwo/OrnamentAmazonTwoStatusPanel.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them public method `setActiveStatus()` ngay tren `OrnamentAmazonTwoStatusPanel`.
- Khi bi goi tren component con, method nay cap nhat `status`, reset page va dispatch event len parent de dong bo active tab.
- Chay them `php artisan optimize:clear` de production nhan code/view moi ngay, tranh JS/view cache cu.

**Root cause:**  
- Production van con request Livewire goi `setActiveStatus` vao component con `pages.ornament-amazon-two.ornament-amazon-two-status-panel`; neu component con khong co method nay thi van 500 du parent da co listener.

**Validation:**  
- `php -l app/Livewire/Pages/OrnamentAmazonTwo/OrnamentAmazonTwoStatusPanel.php` pass.
- `php artisan view:clear --no-ansi` pass.
- `php artisan optimize:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Dong bo Ornament Amazon theo UX/hieu nang cua Ornament Amazon 2.

**File da sua/tao:**  
- `app/Livewire/Pages/OrnamentAmazon/ListOrnamentAmazon.php`
- `app/Livewire/Pages/OrnamentAmazon/OrnamentAmazonStatusPanel.php`
- `resources/views/livewire/pages/ornament-amazon/list-ornament-amazon.blade.php`
- `resources/views/livewire/pages/ornament-amazon/product-design-card.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them `activeStatus` server-side cho Ornament Amazon, giong Amazon 2.
- Trang list chi mount panel cua tab dang mo thay vi render ca 3 panel cung luc.
- Tab switch dung event Livewire + fallback `setActiveStatus()` tren StatusPanel de tranh 500 khi snapshot cu/goi nham component con.
- Card chi `wire:poll.5s` khi automation dang `running`, giong huong toi uu cua Amazon 2.
- Input Image card doi sang cung wrapper `aspect-[4/4.45]`/border/shadow nhu Amazon 2.

**Root cause:**  
- Ornament Amazon con dung luong render/tab cu, tai ca panel an, va poll card ngay ca khi idle nen lag hon Amazon 2.

**Validation:**  
- `php -l app/Livewire/Pages/OrnamentAmazon/ListOrnamentAmazon.php` pass.
- `php -l app/Livewire/Pages/OrnamentAmazon/OrnamentAmazonStatusPanel.php` pass.
- `php -l resources/views/livewire/pages/ornament-amazon/list-ornament-amazon.blade.php` pass.
- `php -l resources/views/livewire/pages/ornament-amazon/product-design-card.blade.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

**Follow-up notes:**  
- Hien da dong bo cac diem UX/hieu nang quan trong. Neu can "y choc" hon nua thi tiep theo nen sync sau: banner auto state chi tiet, retry/continue semantics, Person/Mockup anti-flicker keys, va cac route/controller generation truc tiep nhu Amazon 2.

### 2026-07-10

**Muc tieu:**  
Fix export Camp Auto + Keyword: state dung camp dang chay va Bid phai lay dung tu file nhap.

**File da sua/tao:**  
- `app/Services/Camp/CampAutoExportService.php`
- `app/Services/Camp/CampKeywordExportService.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Camp Auto: khong con hardcode `Bid = 2` o row `Product Targeting`; gio dung bien `$bid` tu `CampRow` cho ca Ad Group va Product Targeting.
- Camp Auto: state campaign/ad group/product ad/product targeting nay dong bo theo target dang active. Hien target `close-match` duoc `enabled`, cac target khac `paused`.
- Camp Keyword: `Bid` duoc normalize tu input (`0.15`, `0,2`, ... -> string xuat ra dung gia tri) thay vi co nguy co sai dinh dang.
- Camp Keyword: `Campaign State (Informational only)` va `Ad Group State (Informational only)` nay la `enabled` de phan anh dung camp/ad group dang chay.
- Camp Keyword: `Start Date` xuat `Ymd` cho dong bo luong export camp moi.

**Root cause:**  
- Auto export con hardcode `U = 2` cho product targeting row va state thong tin chua dung logic camp dang chay. Keyword export chua normalize bid tu input va state thong tin dang de sai.

**Validation:**  
- `php -l app/Services/Camp/CampAutoExportService.php` pass.
- `php -l app/Services/Camp/CampKeywordExportService.php` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-10

**Muc tieu:**  
Sua lai export Camp theo file mau dung `Auto Campaign (1).xlsx` va `Keyword Campaign (2).xlsx`.

**File da sua/tao:**  
- `app/Services/Camp/CampAutoExportService.php`
- `app/Services/Camp/CampKeywordExportService.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doc file mau Auto Campaign: campaign/ad group/product ad cua moi target campaign (`close-match`, `loose-match`, `substitutes`) deu `State` va `Campaign State`/`Ad Group State` la `enabled`.
- Trong moi Auto campaign, 4 row `Product Targeting` chi row co `Product Targeting Expression` trung voi target campaign moi `enabled`; 3 row con lai `paused`. `Campaign State (Informational only)` van `enabled`.
- Auto export giu `Bid` lay tu input cho Ad Group default bid va Product Targeting bid, khong hardcode `2` nua.
- Doc file mau Keyword: `Campaign State (Informational only)` la `paused`; `Ad Group State (Informational only)` chi co `enabled` tren row Ad Group; Product Ad/Keyword de trong AO, AP = 100.
- Keyword export giu `Bid` tu input va format date `Ymd`.

**Root cause:**  
- Lan truoc hieu nham rang target campaign khong active phai paused toan bo. File mau that su tao nhieu campaign enabled, va chi pause/enable tung Product Targeting expression ben trong moi campaign.

**Validation:**  
- `php -l app/Services/Camp/CampAutoExportService.php` pass.
- `php -l app/Services/Camp/CampKeywordExportService.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-13

**Muc tieu:**  
Doi ten Ornamental Amazon thanh Suncatcher cho tuong lai mo rong nhieu design tren cung trang.

**File da sua/tao:**  
- `app/Support/ProductRegistry.php`
- `app/Livewire/Pages/OrnamentAmazon/ListOrnamentAmazon.php`
- `routes/web.php`
- `resources/views/livewire/layout/navigation.blade.php`
- `resources/views/livewire/pages/ornament-amazon/automation-catalog.blade.php`
- `app/Livewire/Pages/Admin/ListUser.php`
- `app/Livewire/Modals/Admin/EditImportTemplate.php`
- `app/Livewire/Modals/ProductDesign/DeleteIdeaConfirm.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi ten hien thi va label tu `Ornament Amazon` sang `Suncatcher` o registry, navigation, trang catalog, admin template label va modal xoa item.
- Doi route catalog thanh `suncatcher-catalog` nhung giu route cu `ornament-amazon-catalog` de khong vo link cu.
- Doi route product page sang slug/route name moi `offorest.products.suncatcher` va `path` `suncatcher` trong registry, dong thoi giu alias cu `ornament-ornament` de an toan.
- Khong doi data slug trong DB ngay luc nay, chi doi nhan dien/URL va alias route.

**Root cause:**  
- Ten hien thi cu con rang buoc voi `Ornament Amazon`, trong khi user muon mo rong thanh khu vuc chung cho nhieu design sau nay.

**Validation:**  
- `php -l app/Support/ProductRegistry.php` pass.
- `php -l app/Livewire/Pages/OrnamentAmazon/ListOrnamentAmazon.php` pass.
- `php -l routes/web.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database. Co route alias cu nen link cu van hoat dong.

**Queue impact:**  
- Khong co.

### 2026-07-13

**Muc tieu:**  
Port toan bo workflow Ornament Amazon 2 sang Suncatcher nhung giu rieng namespace/data/route.

**File da sua/tao:**  
- `app/Livewire/Pages/OrnamentAmazon/ListOrnamentAmazon.php`
- `app/Livewire/Pages/OrnamentAmazon/OrnamentAmazonStatusPanel.php`
- `app/Livewire/Pages/OrnamentAmazon/ProductDesignCard.php`
- `app/Livewire/Pages/OrnamentAmazon/WorkflowActionButton.php`
- `app/Http/Controllers/OrnamentAmazonWorkflowImageController.php`
- `app/Services/OrnamentAmazon/OrnamentAmazonService.php`
- `resources/views/livewire/pages/ornament-amazon/*`
- `routes/web.php`
- `public/js/ornament-amazon-mockup-b5.js`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Sao chep bo flow Amazon 2 sang module Suncatcher/OrnamentAmazon goc.
- Doi namespace/class/view event/slug sang `ornament` va nhan dien hien thi thanh `Suncatcher`.
- Them route mockup download cho Suncatcher va giu alias route cu de khong vo link.
- Tat ca action/nut/step trong UI duoc port theo Amazon 2, nhung backend van tach rieng cho Suncatcher.

**Root cause:**  
- User muon Suncatcher co day du 6 step va nut bam giong Amazon 2, nhung van can hoat dong lap va du lieu rieng, khong dung chung voi `ornament-amazon-2`.

**Validation:**  
- `php -l app/Livewire/Pages/OrnamentAmazon/ListOrnamentAmazon.php` pass.
- `php -l app/Livewire/Pages/OrnamentAmazon/OrnamentAmazonStatusPanel.php` pass.
- `php -l app/Livewire/Pages/OrnamentAmazon/ProductDesignCard.php` pass.
- `php -l app/Livewire/Pages/OrnamentAmazon/WorkflowActionButton.php` pass.
- `php -l app/Http/Controllers/OrnamentAmazonWorkflowImageController.php` pass.
- `php -l app/Services/OrnamentAmazon/OrnamentAmazonService.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database. Co route alias cu nen link cu van hoat dong.

**Queue impact:**  
- Khong co.

### 2026-07-13

**Muc tieu:**  
Fix loi missing Livewire modal sau khi port Amazon 2 workflow sang Suncatcher.

**File da sua/tao:**  
- `app/Livewire/Modals/OrnamentAmazon/ExcelImportOrnament.php`
- `app/Livewire/Modals/OrnamentAmazon/ImportSheet.php`
- `app/Livewire/Modals/OrnamentAmazon/EditImportSheet.php`
- `resources/views/livewire/modals/ornament-amazon/excel-import-ornament.blade.php`
- `resources/views/livewire/modals/ornament-amazon/import-sheet.blade.php`
- `resources/views/livewire/modals/ornament-amazon/edit-import-sheet.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Copy 3 modal tu Amazon 2 sang OrnamentAmazon/Suncatcher: import excel, import sheet, edit import sheet.
- Doi namespace/component/view string tu `ornament-amazon-two` sang `ornament-amazon` de list page mount duoc day du cac modal moi.

**Root cause:**  
- Khi copy giao dien list/product card tu Amazon 2, page Suncatcher goi cac modal moi ma module cu chua co class/component tuong ung, gay `ComponentNotFoundException`.

**Validation:**  
- `php -l app/Livewire/Modals/OrnamentAmazon/ExcelImportOrnament.php` pass.
- `php -l app/Livewire/Modals/OrnamentAmazon/ImportSheet.php` pass.
- `php -l app/Livewire/Modals/OrnamentAmazon/EditImportSheet.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Khong doi database.

**Queue impact:**  
- Khong co.

### 2026-07-13

**Muc tieu:**  
Doi toan bo module Ornament Amazon cu sang Suncatcher, giu nguyen Ornament Amazon 2.

**File da sua/tao:**  
- `app/Support/ProductRegistry.php`
- `routes/web.php`
- `app/Http/Controllers/SuncatcherWorkflowImageController.php`
- `app/Livewire/Pages/Suncatcher/*`
- `app/Livewire/Modals/Suncatcher/*`
- `app/Services/Suncatcher/*`
- `resources/views/livewire/pages/suncatcher/*`
- `resources/views/livewire/modals/suncatcher/*`
- `resources/views/layouts/app.blade.php`
- `public/js/suncatcher-mockup-b5.js`
- `database/migrations/2026_07_13_000100_rename_ornament_product_to_suncatcher.php`

**Thay doi chinh:**  
- Doi product goc tu `ornament` sang `suncatcher` trong registry + route + UI + Livewire + service.
- Tao route workflow moi cho `/offorest/suncatcher/workflow/*` va giu `ornament-amazon-2` nguyen trang.
- Them migration doi `products` slug/name/description sang Suncatcher.
- Chuyen modal import cua Suncatcher sang ten `excel-import-suncatcher`.

**Root cause:**  
- Module goc van dung ten ornament lan voi Amazon 2, lam route/component/UI khong dong bo.

**Validation:**  
- `php -l` pass cho cac file chinh.
- `php artisan route:list --path=suncatcher` pass.
- `php artisan route:list --path=ornament-amazon-2` van giu nguyen.
- `php artisan migrate --pretend` hien migration doi slug sang `suncatcher`.

**Deploy impact:**  
- Can chay migration moi de doi product slug/name.
- Can `composer dump-autoload`, `php artisan optimize:clear` va restart queue/web.

**Queue impact:**  
- Queue/workflow Suncatcher se dung queue/prefix moi; can restart worker sau deploy.

**Follow-up:**  
- Cac file shared voi Amazon 2 van can soi lai neu muon doi tiep label noi dung, vi toi da giu Amazon 2 theo dung yeu cau.

### 2026-07-13

**Muc tieu:**  
Fix viec page Suncatcher khong hien/quyen user van thay Ornament Amazon trong add/edit user truoc khi chay migration slug moi.

**File da sua/tao:**  
- `app/Models/User.php`
- `app/Repositories/Product/ProductRepository.php`
- `app/Models/Product.php`
- `app/Services/User/UserAccessService.php`
- `resources/views/livewire/modals/admin/add-user.blade.php`
- `resources/views/livewire/modals/admin/edit-user.blade.php`
- `resources/views/livewire/pages/admin/list-user.blade.php`
- `resources/views/livewire/layout/navigation.blade.php`

**Thay doi chinh:**  
- Them tuong thich tam thoi cho slug `suncatcher` map sang ca `ornament` de user mo page duoc ngay ca khi DB chua migrate.
- Them `display_name` cho product de admin/add/edit user va navigation hien `Suncatcher` thay vi `Ornament Amazon` neu DB van con slug cu.
- Khi sync quyen product, neu chon Suncatcher thi dong bo ca id `ornament/suncatcher` de tranh mat quyen trong giai doan chuyen doi.

**Root cause:**  
- Code da doi route/slug sang `suncatcher` nhung DB product/quyen user co the van dang la `ornament`, nen menu va check access lech nhau.

**Validation:**  
- `php -l app/Models/User.php` pass.
- `php -l app/Repositories/Product/ProductRepository.php` pass.
- `php -l app/Models/Product.php` pass.
- `php -l app/Services/User/UserAccessService.php` pass.
- `php artisan route:list --path=suncatcher` pass.

**Deploy impact:**  
- Van nen chay migration `2026_07_13_000100_rename_ornament_product_to_suncatcher.php` de dong bo DB that su.

**Queue impact:**  
- Khong co.

### 2026-07-13

**Muc tieu:**  
Fix loi `Route [offorest.products.ornament] not defined` khi vao Suncatcher Catalog.

**File da sua/tao:**  
- `resources/views/livewire/layout/navigation.blade.php`
- `app/Livewire/Pages/Suncatcher/AutomationCatalog.php`
- `app/Services/Suncatcher/SuncatcherAutomationService.php`
- `app/Services/Suncatcher/SuncatcherService.php`
- `resources/views/livewire/pages/suncatcher/automation-catalog.blade.php`

**Thay doi chinh:**  
- Navigation map product slug cu `ornament` sang route slug moi `suncatcher` khi build link/active state.
- Sua mobile navigation Page/Idea loop bi lech sau rename.
- Suncatcher Catalog va automation service tiep tuc dung bang chung `data_ornament_amazon`, khong tim `data_suncatcher` nua.

**Root cause:**  
- DB/quyen user van co product slug `ornament`, layout build route `offorest.products.ornament` trong khi route moi la `offorest.products.suncatcher`.

**Validation:**  
- `php -l resources/views/livewire/layout/navigation.blade.php` pass.
- `php artisan view:clear --no-ansi` pass.
- Khong con reference route dynamic truc tiep toi `offorest.products.ornament` trong navigation.

**Deploy impact:**  
- Clear view/cache sau deploy.

**Queue impact:**  
- Khong co.

### 2026-07-13

**Muc tieu:**  
Fix ParseError `unexpected token endif` trong `resources/views/livewire/layout/navigation.blade.php` sau khi sua route Suncatcher.

**File da sua/tao:**  
- `resources/views/livewire/layout/navigation.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Xoa `@endif` du trong mobile sidebar.
- Kiem tra lai can bang Blade `@if/@endif` ve 0.
- Chay `php artisan view:cache` de compile view thanh cong.

**Root cause:**  
- Khi sua dynamic route slug `ornament` -> `suncatcher`, block mobile navigation bi chen sai va du mot `@endif`.

**Validation:**  
- `php -l resources/views/livewire/layout/navigation.blade.php` pass.
- `php artisan view:cache --no-ansi` pass.

**Deploy impact:**  
- Can clear/cache view sau deploy.

**Queue impact:**  
- Khong co.

### 2026-07-13

**Muc tieu:**  
Fix loi Laravel khong rename duoc compiled view trong `storage/framework/views` gay HTTP 500 dashboard.

**File da sua/tao:**  
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Khong sua code.
- Go co read-only trong `storage/framework/views`, xoa file `.tmp` bi ket va rebuild view cache.

**Root cause:**  
- Windows/Laravel bi ket file temp compiled view (`*.tmp`) trong `storage/framework/views`, lam `rename()` bao `Access is denied (code: 5)`.

**Validation:**  
- `php artisan view:clear --no-ansi` pass.
- `php artisan view:cache --no-ansi` pass.
- `php -l app/Models/User.php`, `routes/web.php`, `navigation.blade.php` pass.

**Deploy impact:**  
- Neu lap lai tren server/local, clear `storage/framework/views/*.tmp` va dam bao web process co quyen ghi folder views.

**Queue impact:**  
- Khong co.

### 2026-07-14

**Muc tieu:**  
Doi rule luu marketplace/Amazon metadata theo gioi han moi cua user.

**File da sua/tao:**  
- `app/Repositories/Product/ProductDesignAssetRepository.php`
- `app/Services/Marketplace/MarketplaceListingMetadataService.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Khi luu metadata vao DB: `title` toi da 199 ky tu va moi tu trong title chi duoc giu toi da 2 lan.
- `description` doi tu 1999 xuong 199 ky tu de dam bao `< 200`.
- `bullet_point_1..5` giu 699 ky tu de dam bao `< 700`.
- `generic_keyword` giu 249 ky tu de dam bao `< 250`.
- Cap nhat service generate Amazon metadata de lay `description` 199 va `generic_keyword` 249 truoc khi goi repository.

**Root cause:**  
- Rule cu dang dung description 1999 va title chi cat length, chua loc tu lap qua 2 lan.

**Validation:**  
- `php -l app/Repositories/Product/ProductDesignAssetRepository.php` pass.
- `php -l app/Services/Marketplace/MarketplaceListingMetadataService.php` pass.

**Deploy impact:**  
- Khong doi database. Can deploy code va clear cache neu server dang cache.

**Queue impact:**  
- Cac job/listing metadata moi se luu theo rule moi. Queue worker can restart de nap code moi.

**Follow-up:**  
- Chua cat lai du lieu cu trong DB; neu user muon thi can chay script cleanup cac row da ton tai.

### 2026-07-14

**Muc tieu:**  
Chuan hoa du lieu metadata cu cho `user_id=1`, `product_id=3` de test theo rule moi.

**File da sua/tao:**  
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Chay script one-off cap nhat 257 row trong `product_design_assets` cho `user_id=1`, `product_id=3`.
- Cat `title` ve toi da 199 ky tu va loai bo tu lap qua 2 lan.
- Cat `description` ve 199 ky tu.
- Cat `bullet_point_1..5` ve 699 ky tu.
- Cat `generic_keyword` ve 249 ky tu.
- Verify lai: khong con row nao vuot gioi han va khong con title nao co tu lap qua 2 lan.

**Root cause:**  
- Rule moi da ap vao code nhung du lieu cu trong DB van dang theo rule cu (`description` 1999, v.v.).

**Validation:**  
- `total=258`, `updated=257`.
- Max sau khi chuan hoa: `title=199`, `description=199`, `bullet_point_1..5=699`, `generic_keyword=249`.
- `title_repeat_violations=0`.

**Deploy impact:**  
- Khong doi schema. Day la data cleanup mot lan tren local DB.

**Queue impact:**  
- Khong co.

**Follow-up:**  
- Neu muon ap dung cho product/user khac hoac production, can chay script tuong tu theo filter mong muon.

### 2026-07-14

**Muc tieu:**  
Them filter chon user cho trang Marketplace Export theo quyen admin/manager.

**File da sua/tao:**  
- `app/Livewire/Pages/Marketplace/MarketplaceExports.php`
- `resources/views/livewire/pages/marketplace/marketplace-exports.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them state session `selectedOwnerUserId` mac dinh `all`.
- Admin/manager thay dropdown User trong filter Marketplace Export.
- Admin xem/chon duoc tat ca user.
- Manager xem/chon duoc tat ca user khong phai admin (`is_admin=false`, `role!='admin'`).
- Non-admin van chi thay du lieu cua chinh ho nhu cu.
- Bo gioi han cu khien manager chi export duoc item cua chinh minh.
- Khi doi user filter thi clear selection va reset page.

**Root cause:**  
- Marketplace Export truoc do khong co filter user rieng; manager query co the thay nhieu user nhung export sheet lai bi gioi han ve auth user, va manager chua loai admin ro rang.

**Validation:**  
- `php -l app/Livewire/Pages/Marketplace/MarketplaceExports.php` pass.
- `php -l resources/views/livewire/pages/marketplace/marketplace-exports.blade.php` pass.
- `php artisan view:clear` pass.

**Deploy impact:**  
- Khong doi database. Can deploy code va clear view/cache.

**Queue impact:**  
- Khong co.

**Follow-up:**  
- Test UI voi admin va manager de xac nhan manager khong thay admin trong dropdown.

### 2026-07-14

**Muc tieu:**  
Fix Marketplace Export user filter thieu kha nang manager tu xem chinh minh.

**File da sua/tao:**  
- `app/Livewire/Pages/Marketplace/MarketplaceExports.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Dropdown user cho manager van loai admin nhung luon include `auth()->id()` bang `orWhereKey(auth()->id())`.
- Query du lieu Marketplace Export cua manager cung luon include item cua chinh manager bang `orWhere('user_id', auth()->id())`.
- Xoa BOM bi PowerShell chen vao file PHP va chay lai syntax check.

**Root cause:**  
- Filter manager chi dua vao `is_admin=false` va `role!='admin'`; neu account manager co flag/role dac biet thi co the bi loai khoi danh sach va query cua chinh minh.

**Validation:**  
- `php -l app/Livewire/Pages/Marketplace/MarketplaceExports.php` pass.
- `php artisan view:clear` pass.

**Deploy impact:**  
- Khong doi database. Can deploy code va clear view/cache.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Port cac chuc nang con thieu cua Ornament Amazon 2 sang Suncatcher.

**File da sua/tao:**  
- `app/Services/Suncatcher/SuncatcherService.php`
- `app/Livewire/Modals/Suncatcher/AddProductDesign.php`
- `app/Livewire/Modals/Suncatcher/ImportExcelSuncatcher.php`
- `resources/views/livewire/modals/suncatcher/import-excel-suncatcher.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- So sanh method-level giua Ornament Amazon 2 va Suncatcher.
- Them `SuncatcherService::applyImportedMockups()` de import Excel co mockup map vao workflow images nhu Ornament Amazon 2.
- Them `AddProductDesign::updatedSku()` de validate SKU trung trong Suncatcher nhu Ornament Amazon 2.
- Tao modal `ImportExcelSuncatcher` va view `import-excel-suncatcher` tu modal import Excel phu cua Ornament Amazon 2, da doi namespace/service/component sang Suncatcher.

**Root cause:**  
- Sau rename/port ban dau, Suncatcher da co phan lon workflow Amazon 2 nhung con thieu method `applyImportedMockups`, hook validate SKU va mot modal import Excel phu.

**Validation:**  
- Method diff cac class chinh khong con missing method so voi Ornament Amazon 2.
- `php -l` pass cho toan bo `app/Livewire/Pages/Suncatcher`, `app/Livewire/Modals/Suncatcher`, `app/Services/Suncatcher`.
- `php artisan route:list --path=suncatcher --no-ansi` pass.
- `php artisan route:list --path=ornament-amazon-2 --no-ansi` pass.

**Deploy impact:**  
- Can clear view/cache va restart worker neu dang co queue workflow Suncatcher.

**Queue impact:**  
- Khong them queue moi; cac workflow Suncatcher tiep tuc dung queue/prefix hien tai.

**Follow-up:**  
- Neu user muon UI co nut rieng cho modal `ImportExcelSuncatcher`, can xac nhan vi hien page dang dung modal `ExcelImportSuncatcher` giong Amazon 2 dang mount.

### 2026-07-16

**Muc tieu:**  
Tach rieng template import Excel cua Suncatcher va Ornament Amazon 2 de admin thay doi doc lap.

**File da sua/tao:**  
- `app/Livewire/Pages/Admin/ListUser.php`
- `app/Livewire/Modals/Admin/EditImportTemplate.php`
- `resources/views/livewire/modals/suncatcher/excel-import-suncatcher.blade.php`
- `resources/views/livewire/modals/ornament-amazon-two/excel-import-ornament.blade.php`
- `storage/app/public/import-templates/suncatcher-import-template.xlsx`
- `storage/app/public/import-templates/ornament-amazon-2-import-template.xlsx`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi `importTemplates()` thanh 2 key rieng: `suncatcher` va `ornament_amazon_two`.
- Admin modal `EditImportTemplate` co 2 template doc lap de upload/sua rieng.
- Modal import cua Suncatcher tai file `suncatcher-import-template.xlsx`.
- Modal import cua Ornament Amazon 2 tai file `ornament-amazon-2-import-template.xlsx`.
- Seed 2 file moi tu file cu `importamaazonxlsx.xlsx` de khong bi mat template hien co.

**Root cause:**  
- Truoc do ca Suncatcher va Ornament Amazon 2 dang dung chung mot file `importamaazonxlsx.xlsx`, khong phu hop khi user muon xu ly logic import rieng cho tung trang.

**Validation:**  
- `php -l app/Livewire/Pages/Admin/ListUser.php` pass.
- `php -l app/Livewire/Modals/Admin/EditImportTemplate.php` pass.
- `php artisan view:cache --no-ansi` pass.
- Da xac nhan ton tai 2 file: `suncatcher-import-template.xlsx`, `ornament-amazon-2-import-template.xlsx`.

**Deploy impact:**  
- Can dam bao symlink `public/storage` hoat dong de link download template moi truy cap duoc.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Cap nhat import Suncatcher them cot `Link Ipnut Main Image` bat buoc, con `Link Main Image` la tuy chon.

**File da sua/tao:**  
- `app/Livewire/Modals/Suncatcher/ExcelImportSuncatcher.php`
- `app/Livewire/Modals/Suncatcher/ImportExcelSuncatcher.php`
- `resources/views/livewire/modals/suncatcher/excel-import-suncatcher.blade.php`
- `resources/views/livewire/modals/suncatcher/import-excel-suncatcher.blade.php`
- `storage/app/public/import-templates/suncatcher-import-template.xlsx`

**Thay doi chinh:**  
- Cot `Link Ipnut Main Image` duoc them vao template va parse/import cua Suncatcher.
- `Link Main Image` van duoc ho tro nhung khong con bat buoc.
- Preview modal da hien thi ro 2 cot anh de user kiem tra truoc khi import.

**Validation:**  
- `php -l` pass cho 2 file Livewire Suncatcher.
- `php artisan view:clear --no-ansi` pass.

**Deploy impact:**  
- Can deploy code va file template xlsx de UI/luong import dong bo.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Noi validation `Campaign Daily Budget` cua Camp de chi can la so, khong bat buoc so nguyen duong.

**File da sua/tao:**  
- `app/Livewire/Pages/Camp/Index.php`
- `app/Livewire/Modals/Camp/ImportCampRows.php`

**Thay doi chinh:**  
- Import Camp dung parse so cho `Campaign Daily Budget`, cho phep so thap phan.
- Validate nhap tay tren page Camp doi sang rule so, khong con bat buoc regex so nguyen duong.
- Luu gia tri budget bang decimal/float.

**Validation:**  
- `php -l app/Livewire/Pages/Camp/Index.php` pass.
- `php -l app/Livewire/Modals/Camp/ImportCampRows.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Fix `Portfolio Id` cua Camp import/export bi them dau cham, dau phay do Excel format locale.

**File da sua/tao:**  
- `app/Livewire/Pages/Camp/Index.php`
- `app/Livewire/Modals/Camp/ImportCampRows.php`
- `app/Services/Camp/CampKeywordExportService.php`
- `app/Services/Camp/CampAutoExportService.php`

**Thay doi chinh:**  
- `normalizePortfolioId()` gio dua `Portfolio Id` ve chuoi chi gom chu so.
- Export Camp Keyword/Auto doi XML cell sang `inlineStr` de Excel giu dang text.
- Import Camp cung loai bo ky tu phan cach locale khi doc `ID portfolio`.

**Validation:**  
- `php -l` pass cho 4 file Camp lien quan.
- `php artisan view:clear --no-ansi` pass.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Doi Suncatcher import de `1. Input Image` lay tu `Link Ipnut Main Image`, khong lay anh dau tien cua `Link Product` nua.

**File da sua/tao:**  
- `app/Livewire/Modals/Suncatcher/ExcelImportSuncatcher.php`

**Thay doi chinh:**  
- `scrapeListingForImport()` khong con bat buoc listing phai co anh.
- `createAsset()` van nhan `input_main_image` tu dong import.
- `Link Product` chi dung lay metadata/phu tro, khong con quyet dinh `Input Image`.

**Validation:**  
- `php -l app/Livewire/Modals/Suncatcher/ExcelImportSuncatcher.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Fix loi Suncatcher import `Unknown named parameter $requireImages`.

**File da sua/tao:**  
- `app/Services/Suncatcher/CompetitorListingScraper.php`
- `app/Livewire/Modals/Suncatcher/ExcelImportSuncatcher.php`

**Thay doi chinh:**  
- Them tham so `bool $requireImages = true` vao `scrape()` cua Suncatcher scraper.
- Import Suncatcher goi `scrape(..., requireImages: false)` de khong bat buoc `Link Product` phai co anh.

**Validation:**  
- `php -l app/Services/Suncatcher/CompetitorListingScraper.php` pass.
- `php -l app/Livewire/Modals/Suncatcher/ExcelImportSuncatcher.php` pass.
- `php artisan view:clear --no-ansi` pass.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Fix user da duoc cap quyen Suncatcher nhung vao trang van bi 403.

**File da sua/tao:**  
- `app/Models/User.php`
- `resources/views/livewire/layout/navigation.blade.php`

**Thay doi chinh:**  
- Bo logic hard-code `suncatcher` chi admin moi vao trong `User::canAccessProduct()`.
- User thuong vao Suncatcher khi co product pivot `suncatcher` trong `product_user`.
- Manager van co full product active, nhung rieng Suncatcher chi hien neu admin gan quyen.

**Root cause:**  
- Middleware `product:suncatcher` goi `canAccessProduct('suncatcher')`, nhung ham nay dang return true chi khi admin/role admin.

**Validation:**  
- `php -l app/Models/User.php` pass.
- `php -l resources/views/livewire/layout/navigation.blade.php` pass.
- `php artisan view:cache --no-ansi` pass sau khi bo read-only cho `bootstrap/cache` local.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Them fallback cho Suncatcher neu DB/pivot tren VPS van con slug cu `ornament`, tranh bi 403 du da cap quyen.

**File da sua/tao:**  
- `app/Models/User.php`
- `resources/views/livewire/layout/navigation.blade.php`

**Thay doi chinh:**  
- `canAccessProduct('suncatcher')` gio chap nhan ca product slug `suncatcher` va `ornament`.
- Navigation manager cung fallback `ornament` de hien Suncatcher dung voi quyen da cap.

**Validation:**  
- `php -l app/Models/User.php` pass.
- `php -l resources/views/livewire/layout/navigation.blade.php` pass.
- `php artisan optimize:clear` pass.
- `php artisan view:cache --no-ansi` pass.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Fix admin bi 403 khi vao Suncatcher.

**File da sua/tao:**  
- `app/Models/User.php`

**Thay doi chinh:**  
- `User::canAccessProduct()` cho admin/role admin `return true` truc tiep.
- User thuong van theo product pivot `suncatcher`/`ornament` nhu da sua.

**Root cause:**  
- Admin branch truoc do van check `products.slug = suncatcher` va active. Neu DB VPS con slug cu `ornament` hoac product row bi lech thi admin bi middleware `product:suncatcher` abort 403.

**Validation:**  
- `php -l app/Models/User.php` pass.
- `php artisan optimize:clear` pass.
- `php artisan view:cache --no-ansi` pass.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Dong bo `Import Sheet` cua Suncatcher voi `Import Excel`: them `Link Ipnut Main Image` bat buoc va `Link Main Image` optional.

**File da sua/tao:**  
- `app/Livewire/Modals/Suncatcher/ImportSheet.php`
- `resources/views/livewire/modals/suncatcher/import-sheet.blade.php`

**Thay doi chinh:**  
- `Import Sheet` parse cot `Link Ipnut Main Image` va dung cot nay cho `1. Input Image`.
- `Link Main Image` chi validate/luu redesign khi co du lieu.
- Preview modal hien ca `Link Ipnut Main Image` va `Link Main Image`.

**Validation:**  
- `php -l app/Livewire/Modals/Suncatcher/ImportSheet.php` pass.
- `php -l resources/views/livewire/modals/suncatcher/import-sheet.blade.php` pass.
- `php artisan view:cache --no-ansi` pass.
- `php artisan optimize:clear` pass.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
An nut `Add Suncatcher` tam thoi va rut gon `Keyword Phrase` trong Import Sheet.

**File da sua/tao:**  
- `resources/views/livewire/pages/suncatcher/list-suncatcher.blade.php`
- `resources/views/livewire/modals/suncatcher/import-sheet.blade.php`

**Thay doi chinh:**  
- Button mo modal add Suncatcher da duoc an/comment, modal van mount de co the bat lai sau.
- Cot `Keyword Phrase` trong preview Import Sheet hien ngan 80 ky tu, co nut `Xem thêm` / `Thu gọn` khi dai.

**Validation:**  
- `php -l resources/views/livewire/pages/suncatcher/list-suncatcher.blade.php` pass.
- `php -l resources/views/livewire/modals/suncatcher/import-sheet.blade.php` pass.
- `php artisan view:cache --no-ansi` pass.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Doi nguon tao `2. Main Image` cua Suncatcher sang `Link Ipnut Main Image`.

**File da sua/tao:**  
- `app/Services/Suncatcher/SuncatcherService.php`

**Thay doi chinh:**  
- `generateRedesign()` uu tien lay anh tu `data_item_add.input_main_image`.
- Chi fallback ve `asset->image_link` neu khong co `input_main_image`.

**Validation:**  
- `php -l app/Services/Suncatcher/SuncatcherService.php` pass.
- `php artisan optimize:clear` pass.
- `php artisan view:cache --no-ansi` pass.

**Queue impact:**  
- Khong co.

### 2026-07-16

**Muc tieu:**  
Hien chi tiet loi khi `Import Sheet` Suncatcher ket thuc voi loi.

**File da sua/tao:**  
- `app/Livewire/Modals/Suncatcher/ImportSheet.php`
- `resources/views/livewire/modals/suncatcher/import-sheet.blade.php`

**Thay doi chinh:**  
- Khi import fail, `showErrors` tu dong bat neu co `rowErrors`.
- Modal them panel loi ben duoi bang, hien `Row ...: message` de user biet loi gi.

**Validation:**  
- `php -l app/Livewire/Modals/Suncatcher/ImportSheet.php` pass.
- `php -l resources/views/livewire/modals/suncatcher/import-sheet.blade.php` pass.
- `php artisan view:cache --no-ansi` pass.

**Queue impact:**  
- Khong co.

### 2026-07-16 15:xx +07:00

**Muc tieu:**  
Dong bo `Import Sheet` cua Ornament Amazon 2 voi logic `Import Excel`, tach rieng voi Suncatcher.

**File da sua/tao:**  
- `app/Livewire/Modals/OrnamentAmazonTwo/ImportSheet.php`
- `resources/views/livewire/modals/ornament-amazon-two/import-sheet.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them parse va validate `Mockup 1` -> `Mockup 6` cho luong import sheet, giong import excel.
- Them `ImageLinkPreviewService` de validate `Link Main Image` va mockup link giong luong import excel, khong dung logic rieng cua Suncatcher.
- Sau khi tao asset tu sheet, neu du 6 mockup hop le thi goi `applyImportedMockups()` giong import excel.
- Cap nhat modal preview import sheet de hien thi day du cot `SKU`, `Product`, `Keyword Phrase`, `Link Product`, `Link Main Image`, `Mockups`, `Status` va danh sach loi chi tiet.

**Root cause:**  
- `ImportSheet` cua Ornament Amazon 2 dang la luong cu, chua parse/mockup/apply mockup, validate link hinh qua long va preview table thieu cot nen khac han `Import Excel`.

**Affected modules:**  
- Ornament Amazon 2 import sheet UI
- Ornament Amazon 2 import sheet parser/validator
- Ornament Amazon 2 imported mockup application flow

**Deploy impact:**  
- Khong can migrate.
- Can clear/refresh view cache neu production dang cache Blade cu.

**Queue impact:**  
- Khong doi queue.

**Kiem tra da chay:**  
- `php -l app/Livewire/Modals/OrnamentAmazonTwo/ImportSheet.php`
- `php -l resources/views/livewire/modals/ornament-amazon-two/import-sheet.blade.php`
- `php artisan view:cache --no-ansi`

**Follow-up notes:**  
- Neu user muon table import sheet giong import excel hon nua (them expand/collapse tung cot mockup, duplicate badge, done badge), co the chinh tiep tren view ma khong anh huong logic import.

### 2026-07-16 15:xx +07:00

**Muc tieu:**  
Fix loi Suncatcher Import Sheet bao `Could not import row: Khong tim thay anh listing tu link nay.`

**File da sua/tao:**  
- `app/Livewire/Modals/Suncatcher/ImportSheet.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi `scrapeListingForImport()` cua Suncatcher Import Sheet tu `$scraper->scrape($url)` sang `$scraper->scrape($url, requireImages: false)`.
- Giu logic Suncatcher: `1. Input Image` va tao anh chinh lay tu `Link Ipnut Main Image`, nen khong bat buoc link product scrape ra anh listing.

**Root cause:**  
- `CompetitorListingScraper::scrape()` mac dinh `requireImages=true`, nen Import Sheet van fail neu trang Amazon/Etsy khong scrape duoc anh listing, du sheet da co `Link Ipnut Main Image` bat buoc.

**Affected modules:**  
- Suncatcher Google Sheet import.

**Deploy impact:**  
- Khong can migrate.
- Chi can deploy code; neu cache class/opcache tren VPS thi reload PHP-FPM/clear optimize neu can.

**Queue impact:**  
- Khong doi queue.

**Kiem tra da chay:**  
- `php -l app/Livewire/Modals/Suncatcher/ImportSheet.php`

**Follow-up notes:**  
- Neu link product khong scrape duoc title/metadata thi van co the fail bang loi `Khong scrape duoc thong tin tu link product`; loi hien tai rieng ve thieu anh listing da duoc bo bat buoc cho Suncatcher sheet.

### 2026-07-16 15:xx +07:00

**Muc tieu:**  
Cho phep Suncatcher chay Auto du chua co `2. Main Image`, va tu tao `2. Main Image` o dau luong.

**File da sua/tao:**  
- `app/Services/Suncatcher/SuncatcherService.php`
- `resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Bo dieu kien bat buoc `$asset->redesign` khi hien nut Auto tren card Suncatcher.
- Trong `startAutomation()`, neu item chua co `redesign` thi goi `generateRedesign()` truoc, sau do moi tao record automation va chay workflow.
- Giu check provider/quota truoc khi tu generate `2. Main Image`.

**Root cause:**  
- UI va service deu dang khoa Auto khi item chua co `2. Main Image`, trong khi user muon Auto tu chay buoc nay truoc roi moi sang Script/Person/Prompt/Mockup.

**Affected modules:**  
- Suncatcher automation start flow
- Suncatcher product card Auto button visibility

**Deploy impact:**  
- Khong can migrate.
- Neu production dang cache Blade/opcache thi clear/reload sau deploy.

**Queue impact:**  
- Auto Suncatcher se co them mot lan generate `2. Main Image` ngay dau workflow neu item chua co `redesign`.

**Kiem tra da chay:**  
- `php -l app/Services/Suncatcher/SuncatcherService.php`
- `php -l resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `php artisan view:cache --no-ansi`

**Follow-up notes:**  
- Neu user muon badge/step hien ro `2. Main Image` la buoc 2 trong auto timeline, can them mot workflow step moi vao UI va record thay vi chi tu generate ngam truoc buoc `3. Script`.

### 2026-07-16 16:xx +07:00

**Muc tieu:**  
Fix nut `Auto` cua Suncatcher dang goi nham luong approve/toggleApproval.

**File da sua/tao:**  
- `app/Livewire/Pages/Suncatcher/ProductDesignCard.php`
- `resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them method `startAutomation()` rieng trong card Suncatcher.
- Doi nut `Auto` tu `wire:click="toggleApproval"` sang `wire:click="startAutomation"`.
- Luong approve van giu rieng qua `confirmApproval()` / `toggleApproval()`.

**Root cause:**  
- UI ghi nhan nut la `Auto` nhung thuc te dang goi `toggleApproval()`, nen bam Auto lai chay vao luong duyet/approval va phat sinh loi `Can co it nhat mot anh mockup hoac lifestyle truoc khi duyet.`

**Affected modules:**  
- Suncatcher card header action buttons
- Suncatcher automation start action

**Deploy impact:**  
- Khong can migrate.
- Neu production cache Blade/opcache thi clear/reload sau deploy.

**Queue impact:**  
- Nut Auto gio se queue dung luong automation thay vi di nham qua approval.

**Kiem tra da chay:**  
- `php -l app/Livewire/Pages/Suncatcher/ProductDesignCard.php`
- `php -l resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `php artisan view:cache --no-ansi`

### 2026-07-16 16:xx +07:00

**Muc tieu:**  
Cho Suncatcher Auto bat dau tu buoc `2. Main Image` thay vi nhay thang vao `3. Script`.

**File da sua/tao:**  
- `app/Services/Suncatcher/SuncatcherService.php`
- `resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them workflow step `main` vao pipeline de dai dien `2. Main Image`.
- Khi chua co `redesign`, Auto se di qua step `main` truoc, roi moi sang `script`.
- UI badge/timeline cua card hien them `2. Main Image` trong workflow Auto.
- `automationStepHasOutput('main')` check theo `filled($asset->redesign)`.

**Root cause:**  
- Auto dang nhay thang vao `3. Script`, nen user bam Auto thay vi vao trang thai dang tao `2. Main Image`. Dieu nay gay cam giac delay va khong ro workflow.

**Affected modules:**  
- Suncatcher automation pipeline
- Suncatcher product design card auto timeline

**Deploy impact:**  
- Khong can migrate.
- Can clear/reload view cache neu production dang cache Blade cu.

**Queue impact:**  
- Workflow Auto se co them step `main` dau tien neu item chua co `redesign`, nhung neu da co anh 2 thi step nay se bo qua nhanh.

**Kiem tra da chay:**  
- `php -l app/Services/Suncatcher/SuncatcherService.php`
- `php -l resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `php artisan view:cache --no-ansi`

**Follow-up notes:**  
- Neu can, co the tinh tiep de UI hien `Auto: 2. Main Image` ro hon va current step chuyen sang `3. Script` sau khi anh 2 xong.

### 2026-07-17 +07:00

**Muc tieu:**  
Kiem tra vi sao `2. Main Image` luu duoc nhung `4. Person A/B` va `6. Mockup` khong luu duoc tren VPS.

**File da sua/tao:**  
- `app/Jobs/GenerateSuncatcherWorkflowImage.php`
- `app/Services/Suncatcher/SuncatcherService.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Phat hien luong Suncatcher workflow chay qua job `GenerateSuncatcherWorkflowImage` va luu file vao `storage/app/public/generated/suncatcher/...`.
- Fix bug job: `GenerateSuncatcherWorkflowImage` phai lay `User` roi moi goi `runAutomationStep($user, ...)` thay vi truyen sai gia tri.
- Xac minh person refs luu vao `generated/suncatcher/workflow/refs` va mockup render luu vao `generated/suncatcher/mockups/{assetId}`.

**Root cause:**  
- Job Suncatcher workflow co bug tham so o pipeline, lam person/mockup co the khong vao dung route xu ly tren queue.
- Tren VPS, worker/permission `storage/app/public` con la diem can duoc dong bo theo user web.

**Affected modules:**  
- Suncatcher queue job processing
- Suncatcher person reference generation
- Suncatcher mockup rendering/output paths

**Deploy impact:**  
- Khong can migrate.
- Can deploy code va chay lai worker Suncatcher.

**Queue impact:**  
- Job `GenerateSuncatcherWorkflowImage` da duoc sua de chay dung pipeline user, giam nguy co step person/mockup bi fail tren queue.

**Kiem tra da chay:**  
- `php -l app/Jobs/GenerateSuncatcherWorkflowImage.php`
- `php -l app/Services/Suncatcher/SuncatcherService.php`
- `rg` kiem tra path `generated/suncatcher/workflow/refs` va `generated/suncatcher/mockups`.

**Follow-up notes:**  
- Neu tren VPS van khong ghi duoc person/mockup thi can xem tiep quyen `storage/app/public`, worker user, va log queue step `person_a/person_b/mockup`.

### 2026-07-17 +07:00

**Muc tieu:**  
Fix Suncatcher de anh tra ve tu worker/API hien ngay tren card trong luc Auto dang chay.

**File da sua/tao:**  
- esources/views/components/image-preview.blade.php
- esources/views/livewire/pages/suncatcher/product-design-card.blade.php
- AI_MEMORY.md

**Thay doi chinh:**  
- Sua component image-preview de dung x-bind:src="currentSrc" thay vi src tinh, giup Livewire poll + Alpine cap nhat URL anh moi ngay khi server tra ve.
- Sua action review image de mo theo currentSrc hien tai thay vi URL cu.
- Them x-effect vao block mockup B5 cua Suncatcher de moi lan Livewire re-render/poll se dong bo lai images, slotStates, slotErrors, unning, doneCount, statusMessage tu server vao Alpine state.

**Root cause:**  
- Card co poll, DB da duoc worker cap nhat, nhung UI preview van giu src HTML cu va mockup grid giu state Alpine cu, nen anh moi khong hien ngay cho den khi reload man hinh.

**Affected modules:**  
- Shared image preview component
- Suncatcher mockup/live automation card

**Deploy impact:**  
- Khong can migrate.
- Can deploy code moi va clear view cache/opcache tren server neu dang cache.

**Queue impact:**  
- Khong doi queue logic; chi sua client-side/live-render de ket qua queue hien ngay khi worker ghi xong.

**Kiem tra da chay:**  
- php -l resources/views/components/image-preview.blade.php
- php -l resources/views/livewire/pages/suncatcher/product-design-card.blade.php
- php artisan view:cache --no-ansi

**Follow-up notes:**  
- Neu tren VPS van thay cham, can check them thoi gian poll va worker co dang ghi file/DB thanh cong hay khong.

### 2026-07-17 +07:00

**Muc tieu:**  
Fix Suncatcher person prompt de khong bat nguoi cam/holding object, dong thoi lam card auto refresh va spinner mockup ro hon.

**File da sua/tao:**  
- `app/Services/Suncatcher/SuncatcherService.php`
- `resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `resources/views/components/image-preview.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them `sanitizePersonReferenceDescription()` de loai bo cac cum tu cam/holding/presenting/receiving li�n quan den san pham trong prompt Person A/B truoc khi gui sang AI.
- Sua fallback prompt Person A/B thanh tay trong, khong cam do.
- Cho `article` poll 5 giay trong ca trang thai `running` va `waiting`.
- Dong bo lai `refUrl`, `refPreviewUrl`, `personGenerating` trong khu person card bang `x-effect` de anh/ref cap nhat ngay khi worker tra ve.
- O mockup 1->6, khi automation dang o step `mockup` thi cac slot chua co anh se duoc set state `generating` de spinner hien ro hon trong luc chay.
- Sua shared image preview component de dung `currentSrc` thay vi src tinh, tranh UI giu URL cu.

**Root cause:**  
- Prompt person cua workflow con bi len nham tu script/fallback cu nen AI van tao nguoi cam/hanh dong voi san pham.
- UI Livewire/Alpine giu state cu nen khi worker/API ghi xong anh moi khong hien ngay.
- Mockup slot state chi duoc sync mot phan tu server, nen co luc slot 6 khong co spinner khi batch dang chay.

**Affected modules:**  
- Suncatcher prompt generation
- Suncatcher Livewire product design card
- Shared image preview component

**Deploy impact:**  
- Khong can migrate.
- Nen deploy code moi va clear view cache/opcache neu production dang cache.

**Queue impact:**  
- Khong doi queue logic.
- UI se nhan thay ket qua queue nhanh hon va spinner mockup ro hon khi worker dang xu ly.

**Kiem tra da chay:**  
- `php -l app/Services/Suncatcher/SuncatcherService.php`
- `php -l resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `php -l resources/views/components/image-preview.blade.php`
- `php artisan view:cache --no-ansi`

**Follow-up notes:**  
- Neu van con prompt cam san pham sau deploy, can kiem tra provider prompt cache/record workflow cu tren DB.

### 2026-07-17 +07:00

**Muc tieu:**  
Fix preview mockup Suncatcher de hien dung 2 action nhu Ornament: generate lai bang prompt chinh va custom image bang prompt nhap tay.

**File da sua/tao:**  
- `resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi event preview Person A/B va mockup Suncatcher tu `review-image` sang `review-image-suncatcher`.
- Mockup preview gio mo dung modal `App\Livewire\Modals\Suncatcher\ReviewImage`, noi da co san nut `Generate` theo prompt B4 va form `Custom This Image` de nhap prompt edit anh.

**Root cause:**  
- Card Suncatcher dang dispatch nham event modal chung `review-image`, nen mo modal Image chung thay vi modal Suncatcher. Modal chung khong hien dung action Suncatcher cho mockup.

**Affected modules:**  
- Suncatcher product design card
- Suncatcher review image modal flow

**Deploy impact:**  
- Khong can migrate.
- Can clear/rebuild Blade cache neu production dang cache.

**Queue impact:**  
- Khong doi queue. Generate lai/custom image van di qua cac method co san cua Suncatcher modal/service.

**Kiem tra da chay:**  
- `php -l resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `php artisan view:cache --no-ansi`

**Follow-up notes:**  
- Neu user van khong thay nut, can kiem tra trang da deploy code moi va event `review-image-suncatcher` co modal listener mounted trong layout.

### 2026-07-17 +07:00

**Muc tieu:**  
Cho panel Suncatcher tu doc database va refresh dung card dang auto (running/waiting) de khong phai reload thu cong.

**File da sua/tao:**  
- `app/Livewire/Pages/Suncatcher/SuncatcherStatusPanel.php`
- `resources/views/livewire/pages/suncatcher/suncatcher-status-panel.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them `wire:poll.5s=\"pollRunningAssets\"` o panel cha.
- `pollRunningAssets()` quet bang `data_ornament_amazon`, loc cac asset dang `workflow_status` = `running` hoac `waiting` trong tab hien tai.
- Panel cha dispatch event `suncatcher-product-design-updated.{assetId}` cho tung card dang chay, de `ProductDesignCard` refresh theo DB thay vi can reload trang.

**Root cause:**  
- Card con co poll rieng, nhung neu trang thai chay thay doi tu DB trong khi card chua duoc hydrate kip, UI co the khong bat dau refresh dung item.
- Can them mot lop poll o panel cha de quet DB va kich hoat refresh dung card dang auto.

**Affected modules:**  
- Suncatcher status panel
- Suncatcher product design card refresh flow

**Deploy impact:**  
- Khong can migrate.
- Can deploy code moi va clear/rebuild Blade cache neu production dang cache.

**Queue impact:**  
- Khong doi queue.
- Chi lam UI bat nhanh ket qua worker/automation dang chay.

**Kiem tra da chay:**  
- `php -l app/Livewire/Pages/Suncatcher/SuncatcherStatusPanel.php`
- `php -l resources/views/livewire/pages/suncatcher/suncatcher-status-panel.blade.php`
- `php artisan view:cache --no-ansi`

**Follow-up notes:**  
- Neu worker da ghi DB sang `completed/failed` thi poll se dung.
- Neu user muon refresh nhanh hon 5s, co the giam poll interval xuong 3s nhung se nang tai hon.

### 2026-07-17 +07:00

**Muc tieu:**  
Fix loi bam Generate/Custom trong preview mockup Suncatcher bi bao Action failed do goi luong dong bo trong Livewire request.

**File da sua/tao:**  
- `app/Livewire/Modals/Suncatcher/ReviewImage.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Doi `generateSuncatcherMockupImage()` tu goi `generateWorkflowImage()` sang `queueWorkflowImageGeneration()` voi queue `suncatcher-priority`.
- Doi `customizeSuncatcherImage()` cho slot mockup sang `queuePreviewWorkflowImageEdit()` voi queue `suncatcher-priority`.
- Toast nay gio bao `Queued!` thay vi `Successfully saved!` de khop voi luong worker.
- Loi he thong/generic error trong preview mockup duoc tra ra ro hon khi queue fail.

**Root cause:**  
- Preview mockup Suncatcher dang render anh dong bo ngay trong Livewire request, trong khi Ornament Amazon 2 da dung queue worker. Dieu nay de gap timeout/lock/exception va bi toast generic `Action failed!`.

**Affected modules:**  
- Suncatcher preview review modal
- Suncatcher workflow image generation/edit queue flow

**Deploy impact:**  
- Khong can migrate.
- Can deploy code moi va clear/rebuild Blade cache neu production dang cache.

**Queue impact:**  
- Preview mockup Generate/Custom nay day vao `suncatcher-priority` de worker xu ly.
- UI se nhan spinner/queued state, khong phai cho request Livewire xong anh ngay lap tuc.

**Kiem tra da chay:**  
- `php -l app/Livewire/Modals/Suncatcher/ReviewImage.php`
- `php artisan view:cache --no-ansi`

**Follow-up notes:**  
- Neu worker suncatcher-priority tren VPS chua chay, nut Generate/Custom se queue xong nhung khong co ket qua.
- Can dam bao queue worker va quyen ghi storage cua user web deu on dinh.

### 2026-07-17 +07:00

**Muc tieu:**  
Them bid vao ten export Camp cho cac cot Campaign Id, Campaign Name, Ad Group Id va Ad Group Name.

**File da sua/tao:**  
- `app/Services/Camp/CampKeywordExportService.php`
- `app/Services/Camp/CampAutoExportService.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them helper `withBidSuffix()` de noi ` - {bid}` vao ten campaign/ad group khi export.
- Camp Keyword: `Campaign Name` va `Ad Group Name` gio de xuat theo format ten + bid.
- Camp Auto: `Campaign Name` va `Ad Group Name` trong tung target type cung duoc them bid suffix.
- `bid` duoc format gon khong du thua so 0 cuoi.

**Root cause:**  
- Export cu giu ten campaign/ad group ngan, khong the hien bid nen ban muon doi format de de nhan biet ngay gia tri bid trong file xuat.

**Affected modules:**  
- Camp keyword export service
- Camp auto export service

**Deploy impact:**  
- Khong can migrate.
- Chi can deploy code va neu co cache view hoac opcache thi clear sau deploy.

**Queue impact:**  
- Khong doi queue.

**Kiem tra da chay:**  
- `php -l app/Services/Camp/CampKeywordExportService.php`
- `php -l app/Services/Camp/CampAutoExportService.php`

**Follow-up notes:**  
- Neu user muon, co the noi tiep bid vao `Campaign Id`/`Ad Group Id` dung ki tu goc hon (vi hien tai helper noi vao ten text).

### 2026-07-17 +07:00

**Muc tieu:**  
Fix preview mockup Suncatcher bi treo trang thai queued/dang quay mai du anh da co roi tren DB.

**File da sua/tao:**  
- `app/Services/Suncatcher/SuncatcherService.php`
- `resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- `markWorkflowImageBatchSlotGenerating()` va `markWorkflowImageBatchSlotFinished()` gio cap nhat them `payload.preview_state` de UI biet slot dang `generating/done/error`.
- UI mockup card khi doc `preview_state` se uu tien `done` neu anh da ton tai, ke ca khi payload cu con `queued`.
- Vi vay truong hop queued cu trong DB khong con lam card quay mai neu mockup da co URL.

**Root cause:**  
- `payload.preview_state` co the con giu `queued` sau khi anh da duoc tao xong, trong khi card lai doc trang thai nay truoc, dan den spinner/queued bi treo sai.

**Affected modules:**  
- Suncatcher automation preview state
- Suncatcher product design card mockup status rendering

**Deploy impact:**  
- Khong can migrate.
- Deploy code moi va clear/rebuild Blade cache neu production dang cache.

**Queue impact:**  
- Khong doi queue.
- Chi dong bo lai trang thai preview de UI khop voi ket qua queue/DB.

**Kiem tra da chay:**  
- `php -l app/Services/Suncatcher/SuncatcherService.php`
- `php -l resources/views/livewire/pages/suncatcher/product-design-card.blade.php`

**Follow-up notes:**  
- Neu job da tao xong anh nhung UI van quay, refresh trang sau deploy se het.
- Neu worker khong chay thi preview_state se con queued; khi do can check queue worker `suncatcher-priority`.

### 2026-07-17 +07:00

**Muc tieu:**  
Bo phu thuoc vao preview status khi render mockup Suncatcher de user test anh theo DB thu.

**File da sua/tao:**  
- `resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Bo doc `automation.payload.preview_state.status` lam nguon trang thai cho mockup slot.
- Mockup card nay chi con dua vao co/khong co anh va trang thai batch trong `images_batch`.
- Neu da co du anh mockup, UI se khong giu spinner/queued do payload cu nua.

**Root cause:**  
- Preview state co the con luu status cu lam UI spin mai du anh da duoc tao xong.
- User can mot che do test thu DB/anh thuan, khong bi status cu chen vao.

**Affected modules:**  
- Suncatcher product design card mockup rendering

**Deploy impact:**  
- Khong can migrate.
- Clear/rebuild Blade cache sau deploy.

**Queue impact:**  
- Khong doi queue.

**Kiem tra da chay:**  
- `php -l resources/views/livewire/pages/suncatcher/product-design-card.blade.php`
- `php artisan view:cache --no-ansi`

**Follow-up notes:**  
- Neu card van spin, can check worker da ghi anh vao `mockup1..6` chua.

### 2026-07-17 +07:00

**Muc tieu:**  
Hien prompt Person A/B trong modal preview anh Suncatcher.

**File da sua/tao:**  
- `app/Livewire/Modals/Suncatcher/ReviewImage.php`
- `AI_MEMORY.md`

**Thay doi chinh:**  
- Them tham so `imagePrompt` vao listener `review-image-suncatcher`.
- Dua `imagePrompt` vao gallery item va set `$this->imagePrompt` khi mo modal.
- Modal da co UI `Prompt Create Image`, nay Person A/B preview se hien prompt neu card truyen len.

**Root cause:**  
- Card Person A/B da dispatch `imagePrompt`, nhung modal Suncatcher `open()` chua khai bao/thiet lap tham so nay nen prompt bi mat.

**Affected modules:**  
- Suncatcher ReviewImage modal
- Suncatcher Person A/B preview flow

**Deploy impact:**  
- Khong can migrate.
- Clear/rebuild Blade cache sau deploy.

**Queue impact:**  
- Khong doi queue.

**Kiem tra da chay:**  
- `php -l app/Livewire/Modals/Suncatcher/ReviewImage.php`
- `php artisan view:cache --no-ansi`
