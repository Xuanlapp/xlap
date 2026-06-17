# Offorest Product Data Model

This document is the current database source of truth for Offorest.

## Current tables

Offorest keeps the product workflow intentionally small:

```text
users
products
product_user
vertex_api_credentials
user_ai_providers
prompts
product_design_assets
psd_mockup_templates
activity_logs
```

Laravel system tables such as `sessions`, `jobs`, `failed_jobs`, `cache`, and `migrations` remain framework-owned tables.

## users

The base Laravel user table also stores Offorest account flags.

- `is_admin`: allows access to admin pages such as `/offorest/admin/users`.

## products

Stores the product/page list shown in the app navigation.

- `id`: primary key.
- `name`: display name, for example Redesign or Mockup.
- `slug`: stable unique key used by routes and permissions.
- `description`: optional admin/help description.
- `is_active`: controls whether the product can be shown/used.
- `created_at`, `updated_at`: timestamps.

Seeded products: `redesign`, `mockup`, `sticker`, `ornament`, `poster`.

## product_user

Pivot table for product access. A user can access many products, and a product can be assigned to many users.

- `id`: primary key.
- `user_id`: user receiving access.
- `product_id`: product being assigned.
- `created_at`, `updated_at`: timestamps.

The table has a unique key on `user_id + product_id`.

## vertex_api_credentials

Stores active Vertex API credentials for image generation and shared marketplace listing generation.

- `id`: primary key.
- `user_id`: owner. User image-generation credentials have a user; shared marketplace credentials can have `null`.
- `function_key`: feature key, for example `image_generation` or `marketplace_listing`.
- `project_id`: Google Cloud project ID.
- `location`: Vertex AI location, for example `global`.
- `client_email`: service account email.
- `private_key`: encrypted private key.
- `credentials_json`: encrypted full service account JSON.
- `is_active`: whether this credential should be used.
- `created_at`, `updated_at`: timestamps.

## user_ai_providers

Stores which AI provider names a user can choose from. The actual model name remains fixed in code/config per provider.

- `id`: primary key.
- `user_id`: user receiving provider access.
- `provider_key`: stable provider key, for example `vertex`, `chatgpt`, or `outsourced`.
- `is_enabled`: whether the user can choose this provider.
- `is_default`: currently selected provider for the user. Exactly one enabled provider should be default.
- `created_at`, `updated_at`: timestamps.

Current provider names are configured in `config/ai_providers.php`.

## prompts

Stores per-user, per-product prompt slots. Each product page can have prompt buttons 1, 2, 3, 4 without hardcoding columns.

- `id`: primary key.
- `user_id`: owner.
- `product_id`: product/page this prompt belongs to.
- `prompt_number`: prompt slot number, usually 1-4.
- `name`: optional prompt label.
- `content`: full prompt text.
- `created_at`, `updated_at`: timestamps.

The table has a unique key on `user_id + product_id + prompt_number`.

Prompt UI rules:

- Prompt management opens from the product page with the shared `openModal` event.
- Current modal: `App\Livewire\Modals\Prompt\DetailPrompt`.
- Current view: `resources/views/livewire/modals/prompt/detail-prompt.blade.php`.
- The modal receives `productSlug`, then loads only prompts owned by the current authenticated user for that product.
- A product page can have at most 4 prompts.
- Show the add `+` button only while the user has fewer than 4 prompts for that product.
- Existing prompts can be edited and saved.
- Prompts cannot be deleted from the UI.
- Default prompt names by slot are: `Design`, `Mockup1`, `Mockup2`, `Mockup3`.

Open modal example:

```blade
wire:click="$dispatch('openModal', { component: 'modals.prompt.detail-prompt', arguments: { productSlug: 'sticker' } })"
```

Save flow:

```text
DetailPrompt modal
 ↓
PromptService
 ↓
PromptRepository
 ↓
prompts table
 ↓
toast success/error
```

## product_design_assets

Stores product row data: keyword, source image link, redesign output, and mockup output links.

- `id`: primary key.
- `user_id`: owner.
- `product_id`: product/page this row belongs to.
- `item_number`: per-user, per-product display number. It starts at 1 for each `user_id + product_id` group and increases automatically when a new row is created.
- `keyword`: keyword from sheet/import/manual entry.
- `image_link`: source image link, stored as `VARCHAR(1000)` because source URLs can be long.
- `redesign`: redesign output link/text.
- `lifestyle1`, `lifestyle2`, `lifestyle3`: Lifestyle Image outputs stored separately from custom PSD mockups.
- `mockup1` through `mockup11`: mockup output links/text.
- `is_approved`: marks items accepted by the user after at least one mockup exists.
- `approved_at`: timestamp for the latest approval action.
- `drive_uploaded_at`: timestamp for the latest successful approved-image export to Google Drive.
- `created_at`, `updated_at`: timestamps.

For the Sticker page workflow:

- `image_link`: source image shown in column 1.
- `redesign`: image generated from `image_link` and prompt number 1, shown in column 2.
- Sticker UI does not use the Lifestyle Image step.
- `mockup1` through `mockup11`: custom PSD mockup slots for column 3. New outputs append to the first empty slot, so the image created first is stored first.

For custom PSD mockups:

- Full renderer and UI documentation: `docs/sticker-psd-mockup.md`.
- The user uploads PSD templates through `App\Livewire\Modals\Sticker\PsdMockupTemplate`.
- A user can store many PSD files, but only one PSD can be active for one function.
- Sticker custom PSD uses `function_key = sticker_custom_mockup`.
- `Generate PSD` uses the active PSD template, the master image from `redesign`, and replaces the PSD layer named `Design`.
- PSD folders named `MOCKUP 1`, `MOCKUP 2`, ... are rendered as PNG outputs by `scripts/psd-renderer/render.js`.
- Rendered PNG outputs append to the first empty `mockup1` through `mockup11` slot. Do not reserve `mockup1` for Lifestyle.
- Approval is allowed only after at least one `mockup1` through `mockup11` value exists.
- Approval is also allowed when at least one `lifestyle1`, `lifestyle2`, or `lifestyle3` value exists.
- Approved items must be unapproved before editing source details.
- Sticker list filters: `all`, `unapproved` (not approved, including items with or without `redesign`), and `approved`.
- Current renderer supports PNG export only. GIF/sheet update can be added later as a separate step.

The Ornament page mirrors the Sticker workflow with its own product slug, Livewire components, prompts, PSD templates, and generated output folders under `generated/ornament/...`.

Approved local images can be exported to Google Drive by schedule or admin action:

- Command: `php artisan offorest:upload-approved-images-to-drive`.
- Schedule: daily at `22:00`, defined in `routes/console.php`.
- Manual trigger: Admin page button `Upload images to Drive`.
- Only local `/storage/...` URLs are uploaded. After upload, the database column is replaced with the Google Drive URL and the local file is deleted.

## activity_logs

Stores audit trail entries for user, admin, and automated system actions.

- `user_id`: actor user when available, nullable for system jobs.
- `actor_type`: `user`, `admin`, or `system`.
- `event`: machine-readable event key, for example `drive_export.image_uploaded`.
- `description`: human-readable summary.
- `subject_type`, `subject_id`: optional affected model.
- `properties`: JSON detail payload for later processing/reporting.
- `ip_address`, `user_agent`: request metadata when available.
- `occurred_at`: event time.

Admin page: `/offorest/admin/logs`.

## psd_mockup_templates

Stores user-uploaded PSD files for custom mockup rendering.

- `id`: primary key.
- `user_id`: owner.
- `product_id`: product/page this PSD belongs to.
- `function_key`: feature key, for example `sticker_custom_mockup`.
- `name`: user-facing PSD name.
- `original_filename`: uploaded filename.
- `storage_path`: path on the public storage disk.
- `is_active`: selected PSD for this user/product/function.
- `created_at`, `updated_at`: timestamps.

Selection rule:

```text
User can have many PSD templates.
User + Product + function_key can have only one active PSD at a time.
Uploading a new PSD for the same function automatically deactivates older PSD files.
```

Render flow:

```text
ProductDesignCard::generatePsdMockups
StickerService
PsdMockupTemplateService active template
PsdMockupRenderer
Node ag-psd renderer
ProductDesignAssetRepository append mockup1..mockup11
toast success/error
```

Sticker data entry uses progressive disclosure:

1. The user enters `keyword` inline on the list page.
2. After submitting the keyword, the page opens the `image_link` input.
3. After saving `image_link`, the source image is shown and the generation controls unlock.

Current Sticker UI is split into a parent page and child card components:

- `App\Livewire\Pages\Sticker\ListSticker`: parent page. It loads the list and mounts each card with a stable Livewire key.
- `App\Livewire\Pages\Sticker\ProductDesignCard`: one card/item. It owns per-item actions such as generate master and generate final images.
- `App\Livewire\Modals\Sticker\AddProductDesign`: add item modal.
- `App\Livewire\Modals\Sticker\EditProductDetail`: edit item modal.

Sticker list pagination:

- `ListSticker` uses Livewire `WithPagination`.
- Available page sizes are `5`, `10`, `20`, `50`, `100`, `200`, `400`.
- The chosen page size is stored with `#[Session(key: 'sticker.per-page')]`.
- Changing page size resets the user to page 1.
- `StickerService::paginatedAssetsForUser()` calls `ProductDesignAssetRepository::paginateForUserAndProduct()`, so the database only loads the current page.
- Each visible row is still mounted as `ProductDesignCard` with key `sticker-product-design-card-{id}`.

Add/edit flow:

```text
Add item:
Add modal -> StickerService -> ProductDesignAssetRepository -> DB
          -> dispatch product-design-created
          -> ListSticker refreshes list and appends the new card
          -> dispatch toast success

Edit item:
Edit modal -> StickerService -> DB
           -> dispatch sticker-product-design-updated with assetId
           -> only matching ProductDesignCard refreshes itself
           -> dispatch toast success
```

This structure is intentional. Do not put all item logic back into `ListSticker`, because that causes the whole page to re-render and can reset unfinished work in other cards.

Image preview supports direct image/CDN URLs and normalizes common shared links such as Google Drive and Dropbox before rendering. The browser loads images through a signed `/image-preview` route, so providers that block normal hotlinking are handled more consistently. If the source URL is a product page instead of an actual image, or the provider blocks server-side fetching, the UI shows a fallback link that opens the original URL.

`id` remains the database primary key and is unique across every user. `item_number` is the visible STT for the user, so different users can both have item number 1 without conflict.

## Relationships

```text
User hasOne VertexApiCredential
User hasMany UserAiProvider
User belongsToMany Product
User hasMany Prompt
User hasMany ProductDesignAsset
User hasMany PsdMockupTemplate

Product belongsToMany User
Product hasMany Prompt
Product hasMany ProductDesignAsset
Product hasMany PsdMockupTemplate
```

## App structure

```text
app/Models/Product.php
app/Models/VertexApiCredential.php
app/Models/UserAiProvider.php
app/Models/Prompt.php
app/Models/ProductDesignAsset.php
app/Models/PsdMockupTemplate.php
app/Actions/CreateUserWithProductAccess.php
app/Actions/ToggleUserProductAccess.php
app/Repositories/Product/ProductRepository.php
app/Repositories/Product/ProductDesignAssetRepository.php
app/Repositories/Product/PsdMockupTemplateRepository.php
app/Repositories/Prompt/PromptRepository.php
app/Repositories/User/UserRepository.php
app/Services/Prompt/PromptService.php
app/Services/Sticker/StickerService.php
app/Services/Sticker/PsdMockupTemplateService.php
app/Services/Sticker/PsdMockupRenderer.php
scripts/psd-renderer/render.js
app/Services/User/UserAccessService.php
app/Services/Image/ImageLinkPreviewService.php
app/Services/Vertex/VertexImageGenerator.php
app/Support/ProductRegistry.php
app/Http/Middleware/EnsureUserIsAdmin.php
app/Livewire/Pages/Admin/ListUser.php
app/Livewire/Pages/Sticker/ListSticker.php
app/Livewire/Pages/Sticker/ProductDesignCard.php
app/Livewire/Modals/Prompt/DetailPrompt.php
app/Livewire/Modals/Sticker/PsdMockupTemplate.php
resources/views/livewire/pages/{product}/{page-name}.blade.php
resources/views/livewire/modals/prompt/detail-prompt.blade.php
resources/views/livewire/modals/sticker/psd-mockup-template.blade.php
resources/views/livewire/pages/admin/list-user.blade.php
```
