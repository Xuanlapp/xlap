# Ornament Amazon 2 Automation

## M?c tiêu

T? d?ng hóa lu?ng `Ornament Amazon 2` sau khi user b?m `Duyet` trên card item.

## Ði?u ki?n kh?i ch?y

- Item ph?i có `2. Main Image`
- User b?m `Duyet`
- H? th?ng t?o/ghi record trong `data_ornament_amazon`

## Flow automation

1. `3. Script`
2. `4. Person A`
3. `4. Person B`
4. `5. Prompt create`
5. `6. Mockup`
6. Hoàn t?t

N?u m?t step l?i:

- d?ng item dó
- ghi `last_error` + `step_errors`
- không ?nh hu?ng item khác

## B?ng d? li?u

Dùng b?ng `data_ornament_amazon` v?i các c?t chính:

- `product_design_asset_id`
- `user_id`
- `product_slug`
- `workflow_name`
- `workflow_status`
- `workflow_step_key`
- `workflow_step_label`
- `workflow_step_number`
- `workflow_total_steps`
- `source_platform`
- `source_link`
- `source_image_link`
- `main_image_link`
- `input_data`
- `step_data`
- `step_errors`
- `last_error`
- `workflow_started_at`
- `workflow_paused_at`
- `workflow_completed_at`

## Queue

Automation ch?y qua queue job riêng:

- `App\Jobs\RunOrnamentAmazonTwoAutomation`

User không ph?i ch? request Livewire ch?y xong.

## Quy u?c UI

- Nút `Duyet` n?m c?nh badge `API`
- `Edit item` không còn hi?n th? ? card
- Khi automation dang ch?y, UI nên ph?n ánh tr?ng thái theo `workflow_status` và `workflow_step_key`

## Luu ý

- Khi d?i flow ho?c step label, ph?i c?p nh?t l?i c? service, job và `docs/memory.md`.
- `review-image` modal dã du?c s?a d? không còn l?i bi?n `$ornamentCustomizeMethod` khi render.



- Item chi duoc completed/approved khi du 6/6 Mockup; thieu bat ky slot nao thi giu trang thai loi de user bam Retry.
- Retry o 6. Mockup phai dua vao du lieu da luu trong DB: slot nao da co mockup1..mockup6 thi bo qua, chi clear va dispatch lai slot con thieu.



- Card button logic uses persisted DB mockup columns as source of truth for Auto / Continue / Retry / Duyet. Duyet only appears after completed + 6/6 DB mockups.

