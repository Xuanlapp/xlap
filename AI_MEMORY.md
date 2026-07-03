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
