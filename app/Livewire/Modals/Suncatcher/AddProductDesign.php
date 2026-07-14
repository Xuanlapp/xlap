<?php

namespace App\Livewire\Modals\Suncatcher;

use App\Livewire\Pages\Suncatcher\ListSuncatcher;
use App\Livewire\Pages\Suncatcher\SuncatcherStatusPanel;
use App\Services\Image\ImageLinkPreviewService;
use App\Services\Suncatcher\CompetitorListingScraper;
use App\Services\Suncatcher\SuncatcherService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class AddProductDesign extends Component
{
    public bool $isOpen = false;

    public string $keyword = '';

    public string $imageLink = '';

    public ?bool $isImageLink = null;

    public string $competitorUrl = '';

    /**
     * @var array{platform?: string, productTitle?: string, link?: string, productDescription?: string, bulletPoints?: array<int, string>, images?: array<int, string>}
     */
    public array $competitorListing = [];

    public string $selectedImageUrl = '';

    public ?string $scrapeError = null;

    /**
     * Open this modal through the shared modal event used by product pages.
     */
    #[On('openModal')]
    public function openModal(string $component, array $arguments = []): void
    {
        if ($component !== 'modals.suncatcher.add-product-design') {
            return;
        }

        $this->open();
    }

    #[On('open-add-product-design')]
    public function open(): void
    {
        $this->resetValidation();
        $this->reset([
            'keyword',
            'imageLink',
            'isImageLink',
            'competitorUrl',
            'competitorListing',
            'selectedImageUrl',
            'scrapeError',
        ]);
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset([
            'isOpen',
            'keyword',
            'imageLink',
            'isImageLink',
            'competitorUrl',
            'competitorListing',
            'selectedImageUrl',
            'scrapeError',
        ]);
    }

    public function updatedImageLink(): void
    {
        $this->isImageLink = $this->imageLink === ''
            ? null
            : $this->looksLikeImageUrl($this->imageLink);
    }

    public function updatedCompetitorUrl(): void
    {
        $url = trim($this->competitorUrl);

        $this->resetValidation();
        $this->scrapeError = null;
        $this->competitorListing = [];
        $this->selectedImageUrl = '';
        $this->keyword = '';
        $this->imageLink = '';
        $this->isImageLink = null;

        if ($url === '') {
            return;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->addError('competitorUrl', 'Link doi thu khong hop le.');
            $this->dispatch('suncatcher-competitor-scrape-finished');

            return;
        }

        if (! $this->isSupportedCompetitorUrl($url)) {
            $this->addError('competitorUrl', 'Chi ho tro link Amazon hoac Etsy.');
            $this->dispatch('suncatcher-competitor-scrape-finished');

            return;
        }

        try {
            $this->competitorListing = app(CompetitorListingScraper::class)->scrape($url);
            $this->keyword = $this->keywordFromTitle($this->competitorListing['productTitle'] ?? '');
            $this->selectedImageUrl = $this->competitorListing['images'][0] ?? '';
            $this->imageLink = $this->selectedImageUrl;
            $this->updatedImageLink();
        } catch (\Throwable $exception) {
            $this->scrapeError = $exception->getMessage();
        }

        $this->dispatch('suncatcher-competitor-scrape-finished');
    }

    private function isSupportedCompetitorUrl(string $url): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return Str::contains($host, 'amazon.') || Str::contains($host, 'etsy.');
    }

    public function selectImage(string $imageUrl): void
    {
        if (! in_array($imageUrl, $this->competitorListing['images'] ?? [], true)) {
            return;
        }

        $this->selectedImageUrl = $imageUrl;
        $this->imageLink = $imageUrl;
        $this->updatedImageLink();
    }

    public function save(): void
    {
        if ($this->competitorListing === [] || $this->keyword === '' || $this->imageLink === '') {
            $this->addError('competitorUrl', 'Hay cho he thong lay xong du lieu roi moi them item.');

            return;
        }

        $validated = $this->validate([
            'keyword' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! Str::contains(Str::lower($value), 'suncatcher')) {
                    $fail("Keyword phai chua tu 'suncatcher' cho trang Suncatcher.");
                }
            }],
            'imageLink' => ['required', 'url', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! app(ImageLinkPreviewService::class)->looksLikeImageUrl($value)) {
                    $fail('Link này chưa giống link ảnh.');
                }
            }],
        ]);

        app(SuncatcherService::class)->createAsset(
            auth()->user(),
            $validated['keyword'],
            $validated['imageLink'],
            $this->secondaryImages($validated['imageLink']),
            $this->competitorListing,
        );

        $this->dispatch('product-design-created')->to(ListSuncatcher::class);
        $this->dispatch('product-design-created')->to(SuncatcherStatusPanel::class);
        $this->dispatch('toast', type: 'success', title: 'Successfully saved!', message: 'Da them item Suncatcher moi.');
        $this->close();
    }

    public function render(): View
    {
        return view('livewire.modals.suncatcher.add-product-design');
    }

    private function looksLikeImageUrl(string $url): bool
    {
        return app(ImageLinkPreviewService::class)->looksLikeImageUrl($url);
    }

    private function keywordFromTitle(string $title): string
    {
        $keyword = trim($title);

        if ($keyword === '') {
            return '';
        }

        if (! Str::contains(Str::lower($keyword), 'suncatcher')) {
            $keyword = trim($keyword.' suncatcher');
        }

        return Str::limit($keyword, 255, '');
    }

    /**
     * Return all scraped source images except the primary image saved in image_link.
     *
     * @return array<int, string>
     */
    private function secondaryImages(string $primaryImageLink): array
    {
        return collect($this->competitorListing['images'] ?? [])
            ->filter(fn (mixed $image): bool => is_string($image))
            ->map(fn (string $image): string => trim($image))
            ->filter(fn (string $image): bool => $image !== '' && $image !== $primaryImageLink)
            ->unique()
            ->values()
            ->all();
    }
}
