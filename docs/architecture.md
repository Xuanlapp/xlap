# ðŸ—ï¸ Project Architecture

**Má»¥c Ä‘Ã­ch:** ÄÃ¢y lÃ  tree chuáº©n cá»§a project. Khi cáº§n hiá»ƒu structure hiá»‡n táº¡i, Ä‘á»c file nÃ y trÆ°á»›c.

## Tree chuáº©n

```text
â”œâ”€â”€ app/
â”‚   â”œâ”€â”€ Actions/
â”‚   â”‚   â”œâ”€â”€ Auth/                    # Logic Jetstream/Fortify: táº¡o user, reset pass
â”‚   â”‚   â””â”€â”€ Team/                    # Logic team Jetstream
â”‚   â”œâ”€â”€ Console/
â”‚   â”‚   â””â”€â”€ Commands/                # Lá»‡nh artisan update NBA/WNBA/MLB data
â”‚   â”œâ”€â”€ Domain/
â”‚   â”‚   â”œâ”€â”€ Sports/
â”‚   â”‚   â”‚   â”œâ”€â”€ MLB/
â”‚   â”‚   â”‚   â”‚   â”œâ”€â”€ Models/
â”‚   â”‚   â”‚   â”‚   â”œâ”€â”€ Services/
â”‚   â”‚   â”‚   â”‚   â”œâ”€â”€ Imports/
â”‚   â”‚   â”‚   â”‚   â”œâ”€â”€ Exports/
â”‚   â”‚   â”‚   â”‚   â””â”€â”€ Livewire/
â”‚   â”‚   â”‚   â”œâ”€â”€ NBA/
â”‚   â”‚   â”‚   â”‚   â”œâ”€â”€ Models/
â”‚   â”‚   â”‚   â”‚   â”œâ”€â”€ Services/
â”‚   â”‚   â”‚   â”‚   â”œâ”€â”€ Imports/
â”‚   â”‚   â”‚   â”‚   â”œâ”€â”€ Exports/
â”‚   â”‚   â”‚   â”‚   â””â”€â”€ Livewire/
â”‚   â”‚   â”‚   â””â”€â”€ WNBA/
â”‚   â”‚   â”‚       â”œâ”€â”€ Models/
â”‚   â”‚   â”‚       â”œâ”€â”€ Services/
â”‚   â”‚   â”‚       â”œâ”€â”€ Imports/
â”‚   â”‚   â”‚       â”œâ”€â”€ Exports/
â”‚   â”‚   â”‚       â””â”€â”€ Livewire/
â”‚   â”‚   â”œâ”€â”€ Programs/
â”‚   â”‚   â”‚   â”œâ”€â”€ Models/
â”‚   â”‚   â”‚   â”œâ”€â”€ Services/
â”‚   â”‚   â”‚   â”œâ”€â”€ Imports/
â”‚   â”‚   â”‚   â”œâ”€â”€ Exports/
â”‚   â”‚   â”‚   â””â”€â”€ Livewire/
â”‚   â”‚   â”œâ”€â”€ Contacts/
â”‚   â”‚   â”‚   â”œâ”€â”€ Models/
â”‚   â”‚   â”‚   â”œâ”€â”€ Services/
â”‚   â”‚   â”‚   â””â”€â”€ Livewire/
â”‚   â”‚   â””â”€â”€ Logos/
â”‚   â”‚       â”œâ”€â”€ Models/
â”‚   â”‚       â”œâ”€â”€ Services/
â”‚   â”‚       â””â”€â”€ Livewire/
â”‚   â”œâ”€â”€ Http/
â”‚   â”‚   â”œâ”€â”€ Controllers/
â”‚   â”‚   â”‚   â”œâ”€â”€ Api/
â”‚   â”‚   â”‚   â”œâ”€â”€ Admin/
â”‚   â”‚   â”‚   â””â”€â”€ Web/
â”‚   â”‚   â”œâ”€â”€ Middleware/
â”‚   â”‚   â””â”€â”€ Requests/
â”‚   â”œâ”€â”€ Livewire/
â”‚   â”‚   â”œâ”€â”€ Shared/
â”‚   â”‚   â”œâ”€â”€ Admin/
â”‚   â”‚   â”œâ”€â”€ Dashboard/
â”‚   â”‚   â””â”€â”€ Modals/
â”‚   â”œâ”€â”€ Models/
â”‚   â”œâ”€â”€ Policies/
â”‚   â”œâ”€â”€ Providers/
â”‚   â””â”€â”€ Support/
â”‚       â”œâ”€â”€ Traits/
â”‚       â”œâ”€â”€ Helpers/
â”‚       â””â”€â”€ Constants/
â”œâ”€â”€ config/
â”‚   â”œâ”€â”€ basketball_logos.php
â”‚   â”œâ”€â”€ repository.php
â”‚   â””â”€â”€ ...
â”œâ”€â”€ database/
â”‚   â”œâ”€â”€ migrations/
â”‚   â”‚   â”œâ”€â”€ auth/
â”‚   â”‚   â”œâ”€â”€ sports/
â”‚   â”‚   â”œâ”€â”€ programs/
â”‚   â”‚   â”œâ”€â”€ contacts/
â”‚   â”‚   â””â”€â”€ logos/
â”‚   â”œâ”€â”€ seeders/
â”‚   â””â”€â”€ factories/
â”œâ”€â”€ docs/
â”‚   â”œâ”€â”€ architecture.md
â”‚   â”œâ”€â”€ authentication.md
â”‚   â”œâ”€â”€ database.md
â”‚   â”œâ”€â”€ import-export.md
â”‚   â”œâ”€â”€ nba.md
â”‚   â”œâ”€â”€ mlb.md
â”‚   â”œâ”€â”€ wnba.md
â”‚   â””â”€â”€ program.md
â”œâ”€â”€ extensions/
â”‚   â””â”€â”€ etsy-crawler-extension/     # Chrome extension bridge cho trang Idea Etsy
â”œâ”€â”€ resources/
â”‚   â”œâ”€â”€ views/
â”‚   â”‚   â”œâ”€â”€ layouts/
â”‚   â”‚   â”œâ”€â”€ components/
â”‚   â”‚   â”‚   â”œâ”€â”€ atomics/
â”‚   â”‚   â”‚   â”œâ”€â”€ molecules/
â”‚   â”‚   â”‚   â”œâ”€â”€ icons/
â”‚   â”‚   â”‚   â””â”€â”€ program/
â”‚   â”‚   â”œâ”€â”€ livewire/
â”‚   â”‚   â”‚   â”œâ”€â”€ shared/
â”‚   â”‚   â”‚   â”œâ”€â”€ sports/
â”‚   â”‚   â”‚   â”‚   â”œâ”€â”€ mlb/
â”‚   â”‚   â”‚   â”‚   â”œâ”€â”€ nba/
â”‚   â”‚   â”‚   â”‚   â””â”€â”€ wnba/
â”‚   â”‚   â”‚   â”œâ”€â”€ programs/
â”‚   â”‚   â”‚   â”œâ”€â”€ contacts/
â”‚   â”‚   â”‚   â””â”€â”€ logos/
â”‚   â”‚   â”œâ”€â”€ admin/
â”‚   â”‚   â”œâ”€â”€ auth/
â”‚   â”‚   â””â”€â”€ profile/
â”‚   â”œâ”€â”€ css/
â”‚   â”œâ”€â”€ js/
â”‚   â””â”€â”€ markdown/
â”œâ”€â”€ routes/
â”‚   â”œâ”€â”€ web.php
â”‚   â”œâ”€â”€ api.php
â”‚   â”œâ”€â”€ admin.php
â”‚   â”œâ”€â”€ sports.php
â”‚   â””â”€â”€ console.php
â”œâ”€â”€ storage/
â”‚   â””â”€â”€ app/
â”‚       â”œâ”€â”€ imports/
â”‚       â”œâ”€â”€ exports/
â”‚       â””â”€â”€ temp/
â”œâ”€â”€ tests/
â”‚   â”œâ”€â”€ Feature/
â”‚   â”œâ”€â”€ Unit/
â”‚   â””â”€â”€ Browser/
â”œâ”€â”€ .env.example
â”œâ”€â”€ .gitignore
â”œâ”€â”€ composer.json
â”œâ”€â”€ package.json
â”œâ”€â”€ README.md
â””â”€â”€ vite.config.js
```

## Quy Æ°á»›c sá»­ dá»¥ng

- `app/Actions/`: logic nghiá»‡p vá»¥ theo nhÃ³m chá»©c nÄƒng.
- `app/Domain/`: business domain chÃ­nh cá»§a app.
- `app/Http/Controllers/Api/`: tráº£ data cho frontend/app ngoÃ i.
- `app/Http/Controllers/Admin/`: module quáº£n trá»‹.
- `app/Http/Controllers/Web/`: render page web chÃ­nh.
- `app/Livewire/Shared/`: component dÃ¹ng chung.
- `routes/admin.php`, `routes/sports.php`: tÃ¡ch route theo domain.
- `docs/`: mÃ´ táº£ kiáº¿n trÃºc, database, import/export, vÃ  spec theo domain.
- `extensions/`: browser extensions duoc version cung repo; `etsy-crawler-extension` la Chrome bridge cho trang Idea Etsy.

## Ghi chÃº cho AI

Khi user nÃ³i "Ä‘á»c doc" hoáº·c há»i vá» tree project, Æ°u tiÃªn:

1. [docs/architecture.md](docs/architecture.md)
2. [docs/CLAUDE.md](docs/CLAUDE.md)
3. TÃ i liá»‡u trong [docs/business-processes/](docs/business-processes)
4. TÃ i liá»‡u trong [docs/connectors/](docs/connectors)
## Vertex AI Gemini

The Vertex AI Gemini API request contract is documented in:

`	ext
docs/vertex-ai-gemini.md
` 

## Product Schema

Current Offorest product workflow uses the simplified product schema documented in:

```text
docs/offorest-data-model.md
```

Sticker custom PSD mockup workflow is documented in:

```text
docs/sticker-psd-mockup.md
```

Current product-domain files:

```text
Route
 ↓
Livewire Page
 ↓
Service
 ↓
Repository
 ↓
Model / Database
 ↓
Blade View
```

For pages with inline work in progress, Livewire state should be preserved with `#[Session]` on safe draft fields. Do not persist sensitive fields such as passwords or secret tokens.

Current application files:

```text
app/Actions/CreateUserWithProductAccess.php
app/Actions/ToggleUserProductAccess.php
app/Livewire/Pages/Admin/ListUser.php
app/Livewire/Pages/Sticker/ListSticker.php
app/Livewire/Pages/Sticker/ProductDesignCard.php
app/Livewire/Pages/Ornament/ListOrnament.php
app/Livewire/Pages/Ornament/ProductDesignCard.php
app/Livewire/Pages/Mockup/Index.php
app/Livewire/Pages/Redesign/Index.php
app/Livewire/Pages/Poster/Index.php
app/Livewire/Pages/IdeaEtsy/IdeaEtsy.php
app/Livewire/Modals/Prompt/DetailPrompt.php
app/Livewire/Modals/Sticker/PsdMockupTemplate.php
app/Livewire/Modals/Ornament/PsdMockupTemplate.php
app/Models/Product.php
app/Models/Prompt.php
app/Models/ProductDesignAsset.php
app/Models/PsdMockupTemplate.php
app/Models/UserAiProvider.php
app/Models/VertexApiCredential.php
app/Repositories/Product/ProductRepository.php
app/Repositories/Product/ProductDesignAssetRepository.php
app/Repositories/Product/PsdMockupTemplateRepository.php
app/Repositories/Prompt/PromptRepository.php
app/Repositories/User/UserRepository.php
app/Services/Prompt/PromptService.php
app/Services/Sticker/StickerService.php
app/Services/Sticker/PsdMockupTemplateService.php
app/Services/Sticker/PsdMockupRenderer.php
app/Services/Ornament/OrnamentService.php
app/Services/Ornament/PsdMockupTemplateService.php
app/Services/Ornament/PsdMockupRenderer.php
app/Services/User/UserAccessService.php
app/Services/Image/ImageLinkPreviewService.php
app/Services/Vertex/VertexImageGenerator.php
app/Support/ProductRegistry.php
app/Http/Middleware/EnsureUserHasProductAccess.php
resources/views/livewire/pages/{product}/{page-name}.blade.php
resources/views/livewire/modals/prompt/detail-prompt.blade.php
resources/views/livewire/modals/sticker/psd-mockup-template.blade.php
resources/views/livewire/pages/admin/list-user.blade.php
resources/views/livewire/pages/idea-test/idea-etsy.blade.php
resources/views/errors/403.blade.php
resources/views/errors/404.blade.php
resources/views/errors/500.blade.php
extensions/etsy-crawler-extension/manifest.json
extensions/etsy-crawler-extension/background.js
extensions/etsy-crawler-extension/bridge.js
```

## Livewire UI Rules

## Product Workflow Docs

- Ornament Amazon automation workflow is documented in `docs/ornament-amazon-automation.md`.
- When editing Ornament Amazon automation, update both `docs/memory.md` and `docs/ornament-amazon-automation.md`.

- Full-page Livewire components must render one root element only.
- For full-page components, prefer `return view(...)->layout('layouts.app')` in the Livewire class instead of wrapping the Blade file in `<x-app-layout>`.
- Keep long lists split into parent page + child item components. Example: `ListSticker` mounts `ProductDesignCard` for each item.
- For long lists, use Livewire `WithPagination` in the parent page and query through the repository with `paginate()`, not `get()`, so large pages do not load every row.
- For modals, use the shared `openModal` event pattern:

```blade
wire:click="$dispatch('openModal', { component: 'modals.sticker.edit-product-detail', arguments: { assetId: {{ $asset->id }} } })"
```

Prompt modal example:

```blade
wire:click="$dispatch('openModal', { component: 'modals.prompt.detail-prompt', arguments: { productSlug: 'sticker' } })"
```

Each modal listens for `openModal`, checks the `component` name, then loads its own data.

```php
#[On('openModal')]
public function openModal(string $component, array $arguments = []): void
{
    if ($component !== 'modals.sticker.edit-product-detail') {
        return;
    }

    $this->open((int) $arguments['assetId']);
}
```

- Add/edit forms should use `wire:submit.prevent="save"` so Livewire handles the request without a normal page submit.
- Temporary success/error feedback should use `x-toast` through the `toast` event. See `docs/ui-components.md`.

## Commands After Code Changes

Run these commands depending on the change:

```bash
# After editing PHP classes
php -l app/Livewire/Pages/Sticker/ListSticker.php
php -l app/Livewire/Pages/Sticker/ProductDesignCard.php
php -l app/Livewire/Modals/Sticker/AddProductDesign.php
php -l app/Livewire/Modals/Sticker/EditProductDetail.php
php -l app/Livewire/Modals/Prompt/DetailPrompt.php
php -l app/Services/Prompt/PromptService.php
php -l app/Repositories/Prompt/PromptRepository.php

# After editing Blade files
php artisan view:clear

# After changing routes/config/events
php artisan optimize:clear

# After changing CSS/JS/Vite assets for production
npm run build

# During local development when CSS/JS changes need hot reload
npm run dev
```

If `php artisan view:cache` fails on Windows with `Access is denied` in `bootstrap/cache`, close processes locking the app files or rerun the terminal with proper permissions. `view:clear` is enough after normal Blade edits during development.

### Ornament Amazon pages
- `ornament` and `ornament-amazon-2` must stay separate product flows.
- Prefer event-driven Livewire updates for add/delete refresh instead of browser reloads.
### Sticker import
- Use `modals.sticker.excel-import-sticker` for Sticker spreadsheet imports; `Keyword` is required.
- Keep Sticker import independent from Ornament Amazon 2 import logic even if the UI shell is similar.